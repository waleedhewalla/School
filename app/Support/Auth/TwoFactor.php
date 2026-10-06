<?php

namespace App\Support\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based one-time codes (authenticator apps) plus single-use recovery
 * codes. Secrets and recovery codes are stored encrypted on the user.
 */
class TwoFactor
{
    public function __construct(private Google2FA $engine) {}

    /** Starts (or restarts) setup: a new secret, not yet confirmed. */
    public function begin(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => encrypt($this->engine->generateSecretKey(32)),
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    /** Confirms setup with a code from the app; returns the recovery codes to show once. */
    public function confirm(User $user, string $code): ?array
    {
        if (! $this->verify($user, $code, allowRecovery: false)) {
            return null;
        }

        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($codes)),
        ])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
    }

    public function enabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && $user->two_factor_secret !== null;
    }

    /** Checks an app code, or (when allowed) uses up one recovery code. */
    public function verify(User $user, string $code, bool $allowRecovery = true): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        $code = trim($code);
        if (preg_match('/^\d{6}$/', $code) && $this->engine->verifyKey(decrypt($user->two_factor_secret), $code, 1)) {
            return true;
        }

        if ($allowRecovery && $user->two_factor_recovery_codes) {
            $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            $index = array_search(Str::lower($code), $codes, true);
            if ($index !== false) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => encrypt(json_encode(array_values($codes)))])->save();

                return true;
            }
        }

        return false;
    }

    public function qrSvg(User $user): string
    {
        $url = $this->engine->getQRCodeUrl(config('app.name'), $user->email, decrypt($user->two_factor_secret));

        return (new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd)))->writeString($url);
    }

    public function secret(User $user): string
    {
        return decrypt($user->two_factor_secret);
    }

    /** For tests: the current code for a user's secret. */
    public function currentCode(User $user): string
    {
        return $this->engine->getCurrentOtp(decrypt($user->two_factor_secret));
    }
}
