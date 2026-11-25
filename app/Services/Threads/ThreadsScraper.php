<?php
declare(strict_types=1);

namespace App\Services\Threads;

final class ThreadsScraper implements ThreadsSource
{
    public function __construct(
        private readonly Fetcher $fetcher = new CurlFetcher(),
        private readonly PageParser $parser = new PageParser(),
        private readonly ScrapeGuard $guard = new ScrapeGuard(),
    ) {
    }

    public function post(string $code): PostData
    {
        if (Identifier::postId($code) === null) throw new \InvalidArgumentException('Kode utas tidak valid');
        return $this->run("post:$code", "/t/$code", fn (Page $page): PostData => $this->parser->post($page, $code));
    }

    public function profile(string $username): ProfileData
    {
        if (Identifier::username($username) !== $username) throw new \InvalidArgumentException('Username tidak valid');
        return $this->run("profile:$username", "/@$username", fn (Page $page): ProfileData => $this->parser->profile($page, $username));
    }

    /**
     * @template T
     * @param callable(Page): T $parse
     * @return T
     */
    private function run(string $target, string $path, callable $parse): mixed
    {
        $this->guard->check();
        $start = hrtime(true);
        $elapsed = static fn (): int => intdiv(hrtime(true) - $start, 1_000_000);
        try {
            $page = $this->fetcher->get($path);
            $data = $parse($page);
        } catch (ThreadsException $error) {
            $this->guard->record($target, $error->outcome, $elapsed(), $error->httpCode, $error->getMessage());
            throw $error;
        }
        $this->guard->record($target, Outcome::Ok, $elapsed(), $page->status);
        return $data;
    }
}
