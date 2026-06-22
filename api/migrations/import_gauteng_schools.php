<?php
/**
 * Gauteng Schools Import Script
 *
 * Imports ~2800 schools from Documentation/Gauteng.csv into the schools table
 * under the GDE (Gauteng Department of Education) organization.
 *
 * Usage: php import_gauteng_schools.php [--dry-run]
 *
 * Prerequisites:
 *   - Migration 027 must be applied (emis_number, district columns on schools)
 *   - GDE organization must exist (created by migration 027)
 *   - Documentation/Gauteng.csv must exist
 *     (convert from Gauteng.xlsx: python3 -c "import openpyxl,csv; ...")
 */

require_once __DIR__ . '/../config/database.php';

// --- Configuration ---
$csvFile = __DIR__ . '/../../Documentation/Gauteng.csv';
$dryRun  = in_array('--dry-run', $argv ?? []);

// --- Helpers ---
function toSlug(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

function mapPhase(?string $phase): string
{
    if ($phase === null) return 'other';
    $p = strtolower($phase);
    if (strpos($p, 'primary') !== false)      return 'primary';
    if (strpos($p, 'secondary') !== false)    return 'secondary';
    if (strpos($p, 'combined') !== false)     return 'combined';
    if (strpos($p, 'intermediate') !== false) return 'primary'; // intermediate maps to primary
    return 'other';
}

function cleanPhone(?string $phone): ?string
{
    if ($phone === null || strtoupper($phone) === 'UNKNOWN' || $phone === '0') return null;
    return preg_replace('/[^0-9+]/', '', (string)$phone) ?: null;
}

function cleanText(?string $val): ?string
{
    if ($val === null) return null;
    $val = trim((string)$val);
    if ($val === '' || strtoupper($val) === 'UNKNOWN' || strtoupper($val) === 'NOT APPLICABLE') {
        return null;
    }
    return $val;
}

// --- Banner ---
echo "==============================================\n";
echo "Gauteng Schools Import" . ($dryRun ? " [DRY RUN]" : "") . "\n";
echo "==============================================\n\n";

// --- Verify CSV ---
if (!file_exists($csvFile)) {
    echo "ERROR: CSV not found at: $csvFile\n";
    echo "Create it with:\n";
    echo "  python3 -c \"\n";
    echo "import openpyxl, csv\n";
    echo "wb = openpyxl.load_workbook('Documentation/Gauteng.xlsx')\n";
    echo "ws = wb.active\n";
    echo "with open('Documentation/Gauteng.csv','w',newline='',encoding='utf-8') as f:\n";
    echo "    writer = csv.writer(f)\n";
    echo "    for row in ws.iter_rows(values_only=True): writer.writerow(row)\n";
    echo "\"\n";
    exit(1);
}

// --- Get GDE organization ID ---
$stmt = $pdo->query("SELECT id FROM organizations WHERE slug = 'gde' LIMIT 1");
$gdeOrg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gdeOrg) {
    echo "ERROR: GDE organization not found. Run migration 027 first:\n";
    echo "  php run_migration_027.php\n";
    exit(1);
}
$gdeOrgId = (int)$gdeOrg['id'];
echo "GDE Organization ID: $gdeOrgId\n\n";

// --- Read CSV ---
$handle = fopen($csvFile, 'r');
if (!$handle) {
    die("ERROR: Cannot open CSV file.\n");
}

// Read header row
$headers = fgetcsv($handle);
if (!$headers) {
    die("ERROR: CSV file is empty.\n");
}

// Map header names to indices
$col = array_flip($headers);

// --- Prepare statements ---
$insertSql = "
    INSERT INTO schools
        (organization_id, name, emis_number, slug, district, school_type,
         phone, address, city, province, country,
         principal_name, total_students, total_teachers, is_active)
    VALUES
        (:organization_id, :name, :emis_number, :slug, :district, :school_type,
         :phone, :address, :city, :province, :country,
         :principal_name, :total_students, :total_teachers, 1)
";

if (!$dryRun) {
    $insertStmt = $pdo->prepare($insertSql);
}

// Check for existing slugs within GDE org
$existingSlugs = [];
$slugStmt = $pdo->query("SELECT slug FROM schools WHERE organization_id = $gdeOrgId");
foreach ($slugStmt->fetchAll(PDO::FETCH_COLUMN) as $s) {
    $existingSlugs[$s] = true;
}

// Check for existing EMIS numbers
$existingEmis = [];
$emisStmt = $pdo->query("SELECT emis_number FROM schools WHERE organization_id = $gdeOrgId AND emis_number IS NOT NULL");
foreach ($emisStmt->fetchAll(PDO::FETCH_COLUMN) as $e) {
    $existingEmis[$e] = true;
}

// --- Stats ---
$imported   = 0;
$skipped    = 0;
$errors     = 0;
$totalRows  = 0;

if (!$dryRun) {
    $pdo->beginTransaction();
}

try {
    while (($row = fgetcsv($handle)) !== false) {
        $totalRows++;

        // Map row values using header indices
        $data = [];
        foreach ($headers as $i => $h) {
            $data[$h] = isset($row[$i]) ? $row[$i] : null;
        }

        // Only import OPEN schools
        $status = strtoupper(trim($data['Status'] ?? ''));
        if ($status !== 'OPEN') {
            $skipped++;
            continue;
        }

        // Extract and clean fields
        $emisRaw  = cleanText($data['NatEmis'] ?? null);
        $emisNum  = $emisRaw ? (string)(int)$emisRaw : null;
        $name     = cleanText($data['Institution_Name'] ?? null);

        if (!$name) {
            $skipped++;
            continue;
        }

        // Skip if EMIS already imported
        if ($emisNum && isset($existingEmis[$emisNum])) {
            $skipped++;
            continue;
        }

        // Generate unique slug
        $baseSlug = toSlug($name);
        $slug     = $baseSlug;
        $suffix   = 2;
        while (isset($existingSlugs[$slug])) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
        $existingSlugs[$slug] = true;
        if ($emisNum) {
            $existingEmis[$emisNum] = true;
        }

        // Map other fields
        $district       = cleanText($data['EIDistrict'] ?? null);
        $schoolType     = mapPhase($data['Phase'] ?? null);
        $phone          = cleanPhone($data['Telephone'] ?? null);
        $streetAddress  = cleanText($data['StreetAddress'] ?? null);
        $city           = cleanText($data['Town_City'] ?? null);
        $principalName  = cleanText($data['Addressee'] ?? null);

        $totalStudentsRaw = $data['LEARNER NUMBER'] ?? null;
        $totalStudents    = ($totalStudentsRaw !== null && is_numeric($totalStudentsRaw)) ? (int)$totalStudentsRaw : 0;

        $totalTeachersRaw = $data['EDUCATOR TOTAL'] ?? null;
        $totalTeachers    = ($totalTeachersRaw !== null && is_numeric($totalTeachersRaw)) ? (int)$totalTeachersRaw : 0;

        $params = [
            ':organization_id' => $gdeOrgId,
            ':name'            => $name,
            ':emis_number'     => $emisNum,
            ':slug'            => $slug,
            ':district'        => $district,
            ':school_type'     => $schoolType,
            ':phone'           => $phone,
            ':address'         => $streetAddress,
            ':city'            => $city,
            ':province'        => 'Gauteng',
            ':country'         => 'South Africa',
            ':principal_name'  => $principalName,
            ':total_students'  => $totalStudents,
            ':total_teachers'  => $totalTeachers,
        ];

        if ($dryRun) {
            if ($imported < 5) {
                echo "  [DRY-RUN] Would insert: $name (EMIS: $emisNum, District: $district, Type: $schoolType)\n";
            }
            $imported++;
        } else {
            try {
                $insertStmt->execute($params);
                $imported++;

                if ($imported % 500 === 0) {
                    echo "  Progress: $imported schools imported...\n";
                }
            } catch (PDOException $e) {
                echo "  ERROR importing '$name': " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }

    if (!$dryRun) {
        $pdo->commit();
    }

} catch (PDOException $e) {
    if (!$dryRun) {
        $pdo->rollBack();
    }
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    echo "Transaction rolled back.\n";
    exit(1);
} finally {
    fclose($handle);
}

// --- Summary ---
echo "\n==============================================\n";
echo "Import Summary" . ($dryRun ? " [DRY RUN - nothing written]" : "") . "\n";
echo "==============================================\n";
echo "Total rows in CSV:    $totalRows\n";
echo "Schools imported:     $imported\n";
echo "Skipped (not OPEN or duplicate): $skipped\n";
echo "Errors:               $errors\n";

if (!$dryRun) {
    $verifyStmt = $pdo->query("SELECT COUNT(*) FROM schools WHERE organization_id = $gdeOrgId");
    $count = $verifyStmt->fetchColumn();
    echo "\nVerification: $count schools now in DB under GDE org.\n";

    $districtStmt = $pdo->query(
        "SELECT district, COUNT(*) as cnt
         FROM schools
         WHERE organization_id = $gdeOrgId AND district IS NOT NULL
         GROUP BY district
         ORDER BY cnt DESC
         LIMIT 10"
    );
    echo "\nTop districts:\n";
    foreach ($districtStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        echo "  {$d['district']}: {$d['cnt']} schools\n";
    }
}

echo "\nDone!\n";
