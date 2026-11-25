<?php
declare(strict_types=1);

function local_base(string $base): string
{
    if (!preg_match('#^https?://(localhost|127\.0\.0\.1|[\w.-]+\.test)(:\d+)?$#D', $base)) exit("Hanya boleh ke server lokal.\n");
    return $base;
}

final class Client
{
    private CurlHandle $curl;
    public int $status = 0;
    public string $body = '';
    public string $headers = '';

    public function __construct(private string $base)
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 15]);
    }

    public function get(string $path): self
    {
        curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        return $this->send($path);
    }

    public function post(string $path, array $data, ?string $formPath = null): self
    {
        if ($formPath) $data['_csrf'] = $this->get($formPath)->csrf();
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($data));
        return $this->send($path);
    }

    public function csrf(): string
    {
        preg_match('/name="_csrf" value="(\w+)"/', $this->body, $m);
        return $m[1] ?? '';
    }

    public function cookie(): string
    {
        $line = curl_getinfo($this->curl, CURLINFO_COOKIELIST)[0] ?? '';
        return substr((string) strrchr($line, "\t"), 1);
    }

    private function send(string $path): self
    {
        curl_setopt($this->curl, CURLOPT_URL, $this->base . $path);
        $raw = (string) curl_exec($this->curl);
        $this->status = curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
        $size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $this->headers = substr($raw, 0, $size);
        $this->body = substr($raw, $size);
        return $this;
    }
}
