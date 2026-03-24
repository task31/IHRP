<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class AppService
{
    /**
     * @param  array<string, mixed>  $oldData
     * @param  array<string, mixed>  $newData
     */
    public static function auditLog(
        string $table,
        int $recordId,
        string $action,
        array $oldData,
        array $newData,
        ?string $description = null,
    ): void {
        DB::table('audit_log')->insert([
            'timestamp' => now(),
            'table_name' => $table,
            'record_id' => $recordId,
            'action_type' => $action,
            'field_changed' => null,
            'old_value' => $oldData === [] ? null : json_encode($oldData),
            'new_value' => $newData === [] ? null : json_encode($newData),
            'description' => $description,
            'user_id' => Auth::id(),
        ]);
    }

    public static function getSetting(string $key, mixed $default = null): mixed
    {
        $row = DB::table('settings')->where('key', $key)->value('value');

        return $row !== null ? $row : $default;
    }

    public static function setSetting(string $key, mixed $value): void
    {
        $now = now();
        $val = $value === null ? null : (string) $value;
        $updated = DB::table('settings')->where('key', $key)->update([
            'value' => $val,
            'updated_at' => $now,
        ]);
        if ($updated === 0) {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $val,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Load SMTP credentials from the settings table and apply them to Laravel's
     * runtime mail config.  Must be called before any Mail::send() dispatch so
     * that admin-configured SMTP values override the .env defaults.
     */
    public static function applySmtpSettings(): void
    {
        $host = self::getSetting('smtp_host');
        if (empty($host)) {
            return; // No SMTP configured — fall back to .env values.
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', (int) self::getSetting('smtp_port', 587));
        Config::set('mail.mailers.smtp.username', self::getSetting('smtp_user', ''));
        Config::set('mail.mailers.smtp.password', self::getSetting('smtp_password', ''));
        Config::set('mail.mailers.smtp.encryption', self::getSetting('smtp_encryption', 'tls'));
        Config::set('mail.from.address', self::getSetting('smtp_from_address', ''));
        Config::set('mail.from.name', self::getSetting('smtp_from_name', config('app.name')));

        // Purge the resolved mailer so the next send() re-instantiates with new config.
        Mail::forgetMailers();
    }
}
