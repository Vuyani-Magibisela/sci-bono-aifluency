<?php
namespace App\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * Send an email via SMTP
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $htmlBody HTML email body
     * @throws \RuntimeException on failure
     */
    public static function send(string $to, string $subject, string $htmlBody): void
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) MAIL_PORT;

            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

            $mail->send();
        } catch (Exception $e) {
            error_log("Mailer error: " . $e->getMessage());
            throw new \RuntimeException("Failed to send email: " . $mail->ErrorInfo);
        }
    }

    /**
     * Build HTML email for a feedback status update sent to the user.
     */
    public static function buildFeedbackUpdateEmail(
        string $userName,
        string $status,
        string $adminResponse,
        string $feedbackSnippet,
        string $siteUrl
    ): string {
        $statusLabels = [
            'in_review' => 'Under Review',
            'resolved'  => 'Resolved',
        ];
        $statusLabel = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));

        $statusColors = [
            'in_review' => '#f59e0b',
            'resolved'  => '#10b981',
        ];
        $statusColor = $statusColors[$status] ?? '#6366f1';

        $snippetHtml = htmlspecialchars(mb_substr($feedbackSnippet, 0, 200), ENT_QUOTES, 'UTF-8');
        if (mb_strlen($feedbackSnippet) > 200) {
            $snippetHtml .= '…';
        }

        $responseHtml = nl2br(htmlspecialchars($adminResponse, ENT_QUOTES, 'UTF-8'));
        $userNameHtml  = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $siteUrlHtml   = htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');

        return "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<title>Update on your feedback – Sci-Bono AI Hub</title>
</head>
<body style='margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f9;padding:32px 0;'>
    <tr><td align='center'>
      <table width='600' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);'>
        <!-- Header -->
        <tr>
          <td style='background:#0f172a;padding:28px 40px;text-align:center;'>
            <h1 style='margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:0.5px;'>Sci-Bono AI Hub</h1>
          </td>
        </tr>
        <!-- Status badge -->
        <tr>
          <td style='padding:32px 40px 0;'>
            <p style='margin:0 0 8px;font-size:14px;color:#64748b;'>Feedback status</p>
            <span style='display:inline-block;padding:6px 16px;border-radius:20px;background:{$statusColor};color:#fff;font-size:13px;font-weight:600;'>{$statusLabel}</span>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style='padding:24px 40px;'>
            <p style='margin:0 0 16px;font-size:16px;color:#1e293b;'>Hi {$userNameHtml},</p>
            <p style='margin:0 0 24px;font-size:15px;color:#475569;line-height:1.6;'>
              Your feedback has been updated to <strong>{$statusLabel}</strong>. Here's a summary:
            </p>

            <!-- Original feedback snippet -->
            <div style='background:#f8fafc;border-left:4px solid #cbd5e1;padding:14px 18px;border-radius:4px;margin-bottom:24px;'>
              <p style='margin:0 0 4px;font-size:12px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;'>Your original message</p>
              <p style='margin:0;font-size:14px;color:#475569;font-style:italic;'>{$snippetHtml}</p>
            </div>

            <!-- Admin response -->
            <div style='background:#f0fdf4;border-left:4px solid {$statusColor};padding:14px 18px;border-radius:4px;margin-bottom:24px;'>
              <p style='margin:0 0 8px;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;'>Response from the team</p>
              <p style='margin:0;font-size:15px;color:#1e293b;line-height:1.6;'>{$responseHtml}</p>
            </div>

            <p style='margin:0 0 24px;font-size:14px;color:#64748b;'>
              If you have further questions, feel free to submit new feedback through the platform.
            </p>

            <a href='{$siteUrlHtml}' style='display:inline-block;background:#6366f1;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;'>
              Go to AI Hub
            </a>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style='padding:24px 40px;border-top:1px solid #e2e8f0;text-align:center;'>
            <p style='margin:0;font-size:12px;color:#94a3b8;'>
              © " . date('Y') . " Sci-Bono Discovery Centre · AI Fluency Platform<br>
              You received this email because you submitted feedback on our platform.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>";
    }
}
