<?php
namespace App\Utils;

/**
 * Validator Utility Class
 *
 * Provides input validation helpers for the API
 */
class Validator
{
    private array $errors = [];
    private array $data;

    /**
     * Constructor
     *
     * @param array $data Data to validate
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Validate that a field is required and not empty
     *
     * @param string $field Field name
     * @param string $message Optional error message
     * @return self
     */
    public function required(string $field, string $message = null): self
    {
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = $message ?? "The {$field} field is required";
        }
        return $this;
    }

    /**
     * Validate email format
     *
     * @param string $field Field name
     * @param string $message Optional error message
     * @return self
     */
    public function email(string $field, string $message = null): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? "The {$field} must be a valid email address";
        }
        return $this;
    }

    /**
     * Validate minimum length
     *
     * @param string $field Field name
     * @param int $length Minimum length
     * @param string $message Optional error message
     * @return self
     */
    public function minLength(string $field, int $length, string $message = null): self
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field] = $message ?? "The {$field} must be at least {$length} characters";
        }
        return $this;
    }

    /**
     * Validate maximum length
     *
     * @param string $field Field name
     * @param int $length Maximum length
     * @param string $message Optional error message
     * @return self
     */
    public function maxLength(string $field, int $length, string $message = null): self
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field] = $message ?? "The {$field} must not exceed {$length} characters";
        }
        return $this;
    }

    /**
     * Validate that a field is numeric
     *
     * @param string $field Field name
     * @param string $message Optional error message
     * @return self
     */
    public function numeric(string $field, string $message = null): self
    {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = $message ?? "The {$field} must be a number";
        }
        return $this;
    }

    /**
     * Validate that a field is an integer
     *
     * @param string $field Field name
     * @param string $message Optional error message
     * @return self
     */
    public function integer(string $field, string $message = null): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
            $this->errors[$field] = $message ?? "The {$field} must be an integer";
        }
        return $this;
    }

    /**
     * Validate that a field is in a list of allowed values
     *
     * @param string $field Field name
     * @param array $allowedValues Allowed values
     * @param string $message Optional error message
     * @return self
     */
    public function in(string $field, array $allowedValues, string $message = null): self
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowedValues, true)) {
            $values = implode(', ', $allowedValues);
            $this->errors[$field] = $message ?? "The {$field} must be one of: {$values}";
        }
        return $this;
    }

    /**
     * Validate that a field matches another field
     *
     * @param string $field Field name
     * @param string $matchField Field to match against
     * @param string $message Optional error message
     * @return self
     */
    public function matches(string $field, string $matchField, string $message = null): self
    {
        if (isset($this->data[$field]) && isset($this->data[$matchField]) &&
            $this->data[$field] !== $this->data[$matchField]) {
            $this->errors[$field] = $message ?? "The {$field} must match {$matchField}";
        }
        return $this;
    }

    /**
     * Validate that a field matches a regex pattern
     *
     * @param string $field Field name
     * @param string $pattern Regex pattern
     * @param string $message Optional error message
     * @return self
     */
    public function pattern(string $field, string $pattern, string $message = null): self
    {
        if (isset($this->data[$field]) && !preg_match($pattern, $this->data[$field])) {
            $this->errors[$field] = $message ?? "The {$field} format is invalid";
        }
        return $this;
    }

    /**
     * Validate URL format
     *
     * @param string $field Field name
     * @param string $message Optional error message
     * @return self
     */
    public function url(string $field, string $message = null): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field] = $message ?? "The {$field} must be a valid URL";
        }
        return $this;
    }

    /**
     * Validate date format
     *
     * @param string $field Field name
     * @param string $format Date format (default: Y-m-d)
     * @param string $message Optional error message
     * @return self
     */
    public function date(string $field, string $format = 'Y-m-d', string $message = null): self
    {
        if (isset($this->data[$field])) {
            $date = \DateTime::createFromFormat($format, $this->data[$field]);
            if (!$date || $date->format($format) !== $this->data[$field]) {
                $this->errors[$field] = $message ?? "The {$field} must be a valid date in format {$format}";
            }
        }
        return $this;
    }

    /**
     * Add a custom validation rule
     *
     * @param string $field Field name
     * @param callable $callback Validation callback that returns true if valid
     * @param string $message Error message
     * @return self
     */
    public function custom(string $field, callable $callback, string $message): self
    {
        if (isset($this->data[$field]) && !$callback($this->data[$field], $this->data)) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    /**
     * Check if validation passes
     *
     * @return bool
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if validation fails
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get a specific field error
     *
     * @param string $field Field name
     * @return string|null
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Static method to validate data
     *
     * @param array $data Data to validate
     * @return self
     */
    public static function make(array $data): self
    {
        return new self($data);
    }

    /**
     * Sanitize a string
     *
     * @param string $value Value to sanitize
     * @return string
     */
    public static function sanitize(string $value): string
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize an email
     *
     * @param string $email Email to sanitize
     * @return string
     */
    public static function sanitizeEmail(string $email): string
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Validate password strength
     *
     * @param string $field Field name
     * @param string $message Optional error message
     * @return self
     */
    public function strongPassword(string $field, string $message = null): self
    {
        if (isset($this->data[$field])) {
            $password = $this->data[$field];
            $minLength = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;

            // Check minimum length
            if (strlen($password) < $minLength) {
                $this->errors[$field] = $message ?? "Password must be at least {$minLength} characters";
                return $this;
            }

            // Check for at least one uppercase letter
            if (!preg_match('/[A-Z]/', $password)) {
                $this->errors[$field] = $message ?? "Password must contain at least one uppercase letter";
                return $this;
            }

            // Check for at least one lowercase letter
            if (!preg_match('/[a-z]/', $password)) {
                $this->errors[$field] = $message ?? "Password must contain at least one lowercase letter";
                return $this;
            }

            // Check for at least one number
            if (!preg_match('/[0-9]/', $password)) {
                $this->errors[$field] = $message ?? "Password must contain at least one number";
                return $this;
            }

            // Check for at least one special character
            if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
                $this->errors[$field] = $message ?? "Password must contain at least one special character";
                return $this;
            }
        }

        return $this;
    }
}
