<?php
declare(strict_types=1);

namespace App\Services\Threads;

/** Membaca JSON yang tertanam di halaman publik Threads. */
final class PageParser
{
    public function post(Page $page, string $code): PostData
    {
        $this->ensureReadable($page);
        $path = (string) parse_url($page->url, PHP_URL_PATH);
        if (!preg_match('~^/@[^/]+/post/([A-Za-z0-9_-]+)/?$~D', $path, $m) || $m[1] !== $code) {
            throw new ThreadsException(Outcome::NotFound, 'Dialihkan dari halaman utas', $page->status);
        }

        $docs = $this->documents($page->body, ['"view_counts"', '"post_id"', '"taken_at"']);
        $media = $this->first($docs, static fn (array $n): bool => ($n['code'] ?? null) === $code
            && isset($n['user']['username'], $n['taken_at']) && (isset($n['user']['pk']) || isset($n['user']['id'])));
        if (!$media) throw new ThreadsException(Outcome::ParseError, 'Node utas tidak ada', $page->status);

        // view_counts milik halaman, bukan node utas. Views opsional: utas baru sering belum memilikinya.
        $props = $this->first($docs, static fn (array $n): bool => isset($n['post_id'], $n['post_author_id']) && is_scalar($n['post_id']));
        $ownerPk = (string) ($media['user']['pk'] ?? $media['user']['id']);
        if ($props && ((string) $props['post_id'] !== Identifier::postId($code) || (string) $props['post_author_id'] !== $ownerPk)) {
            throw new ThreadsException(Outcome::ParseError, 'ID halaman tidak cocok', $page->status);
        }

        return new PostData(
            $code,
            $this->pk($ownerPk, $page),
            $this->usernameOf($media['user']['username'], $page),
            (int) $media['taken_at'],
            is_string($media['caption']['text'] ?? null) ? $media['caption']['text'] : '',
            $props ? $this->views($docs, $page) : null,
            $this->count($media['like_count'] ?? null),
            ($media['text_post_app_info']['is_reply'] ?? false) === true,
            is_string($media['text_post_app_info']['tag_header']['display_name'] ?? null) ? $media['text_post_app_info']['tag_header']['display_name'] : '',
        );
    }

    public function profile(Page $page, string $username): ProfileData
    {
        $this->ensureReadable($page);
        $docs = $this->documents($page->body, ['"biography"', '"user_id"']);
        $user = $this->first($docs, static fn (array $n): bool => is_string($n['username'] ?? null)
            && strtolower($n['username']) === $username
            && array_key_exists('biography', $n) && array_key_exists('bio_links', $n)
            && (isset($n['pk']) || isset($n['id'])));
        if (!$user) throw new ThreadsException(Outcome::ParseError, 'Node profil tidak ada', $page->status);

        $pk = $this->pk((string) ($user['pk'] ?? $user['id']), $page);
        $props = $this->first($docs, static fn (array $n): bool => isset($n['user_id'], $n['is_self_profile']) && is_scalar($n['user_id']));
        if (!$props || (string) $props['user_id'] !== $pk) {
            throw new ThreadsException(Outcome::ParseError, 'ID profil tidak cocok', $page->status);
        }

        $links = [];
        foreach (is_array($user['bio_links']) ? $user['bio_links'] : [] as $link) {
            if (is_string($link['url'] ?? null)) $links[] = $link['url'];
        }
        return new ProfileData(
            $pk,
            $this->usernameOf($user['username'], $page),
            is_string($user['biography']) ? $user['biography'] : '',
            $links,
            ($user['text_post_app_is_private'] ?? false) === true,
        );
    }

    private function ensureReadable(Page $page): void
    {
        if ($page->status === 404 || $page->status === 410) throw new ThreadsException(Outcome::NotFound, '', $page->status);
        if (in_array($page->status, [401, 403, 429], true)) throw new ThreadsException(Outcome::Blocked, '', $page->status);
        if ($page->status !== 200) throw new ThreadsException(Outcome::Network, 'Status HTTP tidak terduga', $page->status);

        // Threads memakai HTTP 200 untuk utas hilang dan halaman login.
        $path = (string) parse_url($page->url, PHP_URL_PATH);
        if (str_starts_with($path, '/login')) throw new ThreadsException(Outcome::LoginRequired, '', 200);
        parse_str((string) parse_url($page->url, PHP_URL_QUERY), $query);
        if (isset($query['error'])) throw new ThreadsException(Outcome::NotFound, '', 200);
    }

    /** Null bila Threads tidak menampilkan angkanya. */
    private function views(array $docs, Page $page): ?int
    {
        $found = [];
        $this->walk($docs, static function (array $node) use (&$found): bool {
            if (array_key_exists('view_counts', $node)) $found[] = $node['view_counts'];
            return false;
        });

        $values = [];
        foreach ($found as $value) {
            if ($value === null) continue;
            $count = $this->count($value);
            if ($count === null) throw new ThreadsException(Outcome::ParseError, 'Views bukan angka', $page->status);
            $values[$count] = true;
        }
        if (count($values) > 1) throw new ThreadsException(Outcome::ParseError, 'Views ganda', $page->status);
        return $values ? array_key_first($values) : null;
    }

    private function count(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    private function pk(string $pk, Page $page): string
    {
        if (!preg_match('/^[0-9]{1,25}$/D', $pk)) throw new ThreadsException(Outcome::ParseError, 'ID akun tidak valid', $page->status);
        return $pk;
    }

    private function usernameOf(mixed $value, Page $page): string
    {
        $username = is_string($value) ? Identifier::username($value) : null;
        return $username ?? throw new ThreadsException(Outcome::ParseError, 'Username tidak valid', $page->status);
    }

    /** Hanya mendekode blok JSON yang relevan, karena satu halaman sekitar 1 MB. */
    private function documents(string $html, array $needles): array
    {
        $docs = [];
        $offset = 0;
        while (($start = strpos($html, '<script type="application/json"', $offset)) !== false) {
            $open = strpos($html, '>', $start);
            $end = $open === false ? false : strpos($html, '</script>', $open);
            if ($end === false) break;
            $json = substr($html, $open + 1, $end - $open - 1);
            $offset = $end;
            foreach ($needles as $needle) {
                if (!str_contains($json, $needle)) continue;
                $data = json_decode($json, true);
                if (is_array($data)) $docs[] = $data;
                break;
            }
        }
        return $docs;
    }

    private function first(array $docs, callable $match): ?array
    {
        $found = null;
        $this->walk($docs, static function (array $node) use ($match, &$found): bool {
            if (!$match($node)) return false;
            $found = $node;
            return true;
        });
        return $found;
    }

    /** Berhenti bila $visit mengembalikan true. */
    private function walk(array $node, callable $visit): bool
    {
        if ($visit($node)) return true;
        foreach ($node as $child) {
            if (is_array($child) && $this->walk($child, $visit)) return true;
        }
        return false;
    }
}
