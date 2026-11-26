<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Validate;
use App\Models\Audit;
use App\Models\Setting;
use App\Models\User;

/** Login Google (OpenID Connect, authorization code dengan PKCE) untuk freelancer. */
final class GoogleLogin
{
    private const AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN = 'https://oauth2.googleapis.com/token';

    /** @param ?\Closure $exchange menerima isian form, mengembalikan jawaban token; untuk tes */
    public function __construct(private readonly ?\Closure $exchange = null)
    {
    }

    public static function enabled(): bool
    {
        return Setting::int('google_on') === 1 && Setting::str('google_id') !== '' && Setting::str('google_secret') !== '';
    }

    public static function callback(): string
    {
        return Seo::base() . '/masuk/google/selesai';
    }

    /** @return array{0: string, 1: array} URL Google dan data yang harus disimpan di sesi */
    public static function begin(?int $linkTo): array
    {
        $flow = ['state' => bin2hex(random_bytes(16)), 'nonce' => bin2hex(random_bytes(16)), 'verifier' => bin2hex(random_bytes(32)), 'link' => $linkTo, 'until' => time() + 600];
        return [self::AUTH . '?' . http_build_query([
            'client_id' => Setting::str('google_id'), 'redirect_uri' => self::callback(), 'response_type' => 'code', 'scope' => 'openid email profile',
            'state' => $flow['state'], 'nonce' => $flow['nonce'], 'code_challenge' => self::base64(hash('sha256', $flow['verifier'], true)), 'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]), $flow];
    }

    /** Menukar kode dan memeriksa klaim. @return array{sub: string, email: string, name: string} */
    public function identity(array $flow, string $state, string $code): array
    {
        if (($flow['until'] ?? 0) < time() || !hash_equals($flow['state'], $state) || $code === '' || strlen($code) > 1000) throw new VerificationException('Sesi Google sudah berakhir. Coba lagi.');
        $fields = ['code' => $code, 'client_id' => Setting::str('google_id'), 'client_secret' => Setting::secret('google_secret'), 'redirect_uri' => self::callback(), 'grant_type' => 'authorization_code', 'code_verifier' => $flow['verifier']];
        $reply = $this->exchange ? ($this->exchange)($fields) : self::request($fields);
        // Token datang langsung dari Google lewat TLS, jadi klaimnya cukup diperiksa.
        $parts = explode('.', is_string($reply['id_token'] ?? null) ? $reply['id_token'] : '');
        $claims = count($parts) === 3 ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true) : null;
        $valid = is_array($claims)
            && in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)
            && ($claims['aud'] ?? '') === Setting::str('google_id')
            && (int) ($claims['exp'] ?? 0) > time()
            && hash_equals($flow['nonce'], (string) ($claims['nonce'] ?? ''))
            && preg_match('/^[0-9A-Za-z]{1,64}$/D', (string) ($claims['sub'] ?? ''));
        if (!$valid) throw new VerificationException('Jawaban Google tidak bisa dipercaya. Coba lagi.');
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        if (($claims['email_verified'] ?? false) !== true || Validate::email($email)) throw new VerificationException('Email Google kamu belum terverifikasi.');
        return ['sub' => (string) $claims['sub'], 'email' => $email, 'name' => mb_substr(Validate::cleanName((string) ($claims['name'] ?? '')), 0, 80)];
    }

    /** Masuk atau mendaftar. @return array baris user */
    public static function resolve(array $google): array
    {
        $db = Database::pdo();
        $st = $db->prepare("SELECT * FROM users WHERE google_sub = ? AND role = 'freelancer'");
        $st->execute([$google['sub']]);
        $user = $st->fetch();
        if ($user) {
            if ($user['status'] !== 'active') throw new VerificationException('Akun ini diblokir. Hubungi admin.');
            return $user;
        }
        // Email akun lama belum tentu pernah dibuktikan, jadi tidak disambungkan diam-diam.
        if (User::findByEmail($google['email'])) throw new VerificationException('Email ini sudah punya akun. Masuk dengan password, lalu hubungkan Google di halaman Keamanan.');
        if (!Setting::int('register_open')) throw new VerificationException('Pendaftaran sedang ditutup.');
        $name = Validate::name($google['name']) ? mb_substr(strstr($google['email'], '@', true) . ' TryCuan', 0, 80) : $google['name'];
        try {
            $id = User::createGoogle($name, $google['email'], $google['sub']);
        } catch (\PDOException $error) {
            if (($error->errorInfo[1] ?? 0) === 1062) throw new VerificationException('Akun Google ini baru saja dipakai. Coba masuk lagi.');
            throw $error;
        }
        Audit::add($id, 'google.registered', "user:$id");
        return User::find($id);
    }

    public static function link(int $userId, array $google): void
    {
        try {
            Database::pdo()->prepare("UPDATE users SET google_sub = ? WHERE id = ? AND google_sub IS NULL AND role = 'freelancer'")->execute([$google['sub'], $userId]);
        } catch (\PDOException $error) {
            if (($error->errorInfo[1] ?? 0) === 1062) throw new VerificationException('Akun Google ini sudah terhubung ke akun lain.');
            throw $error;
        }
        Audit::add($userId, 'google.linked', "user:$userId");
    }

    private static function request(array $fields): array
    {
        $curl = curl_init(self::TOKEN);
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true, CURLOPT_PROTOCOLS_STR => 'https', CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 12]);
        $body = curl_exec($curl);
        $data = is_string($body) ? json_decode($body, true) : null;
        if (curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200 || !is_array($data)) {
            // Hanya kode error; tidak ada token atau secret di log.
            error_log('Google token gagal: ' . (is_array($data) ? substr((string) ($data['error'] ?? ''), 0, 60) : curl_error($curl)));
            throw new VerificationException('Google belum bisa dihubungi. Coba lagi.');
        }
        return $data;
    }

    private static function base64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
