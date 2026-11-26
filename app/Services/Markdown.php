<?php
declare(strict_types=1);

namespace App\Services;

/** Markdown ringkas. Semua teks di-escape lebih dulu, jadi HTML dari penulis tidak pernah lolos. */
final class Markdown
{
    /** @var list<array{id: string, text: string}> judul tingkat dua untuk daftar isi */
    public array $toc = [];
    private int $copy = 0;

    /** @param string $prefix awalan id judul @param string $fileBase alamat berkas campaign */
    public function __construct(private readonly string $prefix = '', private readonly string $fileBase = '/materi/')
    {
    }

    public function render(string $text): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        $html = '';
        $total = count($lines);
        for ($i = 0; $i < $total; $i++) {
            $line = rtrim($lines[$i]);
            if ($line === '') continue;
            if (str_starts_with($line, '```')) {
                $block = [];
                for ($i++; $i < $total && !str_starts_with($lines[$i], '```'); $i++) $block[] = rtrim($lines[$i]);
                $id = "{$this->prefix}salin-" . ++$this->copy;
                $html .= '<div class="copy-box"><div class="row"><span>' . e(trim(substr($line, 3)) ?: 'Teks siap salin') . '</span><button class="text-btn" type="button" data-copy="' . e($id) . '">' . icon('copy') . 'Salin</button></div><pre id="' . e($id) . '">' . e(implode("\n", $block)) . '</pre></div>';
            } elseif (preg_match('/^(#{1,4})\s+(.+)$/u', $line, $m)) {
                $level = max(2, min(4, strlen($m[1])));
                $id = $this->prefix . (trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($m[2])), '-') ?: 'bagian') . '-' . (count($this->toc) + 1);
                if ($level === 2) $this->toc[] = ['id' => $id, 'text' => $m[2]];
                $html .= "<h$level" . ($level === 2 ? ' id="' . e($id) . '"' : '') . '>' . $this->inline($m[2]) . "</h$level>";
            } elseif (preg_match('/^(-{3,}|\*{3,})$/', $line)) {
                $html .= '<hr>';
            } elseif (str_starts_with($line, '>')) {
                $quote = [];
                for (; $i < $total && str_starts_with($lines[$i], '>'); $i++) $quote[] = $this->inline(ltrim(substr(rtrim($lines[$i]), 1)));
                $i--;
                $html .= '<blockquote><p>' . implode('<br>', $quote) . '</p></blockquote>';
            } elseif (preg_match('/^\s*([-*]|\d+\.)\s+/', $line, $m)) {
                $tag = ctype_digit($m[1][0]) ? 'ol' : 'ul';
                $mark = $tag === 'ol' ? '/^\s*\d+\.\s+(.*)$/u' : '/^\s*[-*]\s+(.*)$/u';
                $items = '';
                for (; $i < $total && preg_match($mark, rtrim($lines[$i]), $n); $i++) {
                    $text = $n[1];
                    $box = '';
                    if (preg_match('/^\[([ xX])\]\s+(.*)$/u', $text, $c)) [$box, $text] = ['<span class="box' . ($c[1] === ' ' ? '' : ' on') . '">' . icon('check') . '</span>', $c[2]];
                    $items .= '<li' . ($box ? ' class="todo"' : '') . ">$box<span>" . $this->inline($text) . '</span></li>';
                }
                $i--;
                $html .= "<$tag>$items</$tag>";
            } else {
                $paragraph = [];
                for (; $i < $total && trim($lines[$i]) !== '' && !preg_match('/^(#{1,4}\s|```|>|\s*([-*]|\d+\.)\s|-{3,}$)/', $lines[$i]); $i++) $paragraph[] = $this->inline(rtrim($lines[$i]));
                $i--;
                // Baris yang tidak cocok pola mana pun tetap ditulis, supaya tidak berulang.
                if (!$paragraph) $paragraph[] = $this->inline(rtrim($lines[++$i]));
                $html .= '<p>' . implode('<br>', $paragraph) . '</p>';
            }
        }
        return $html;
    }

    private function inline(string $text): string
    {
        $keep = [];
        // Penanda memakai byte kendali yang dibuang lebih dulu dari isi.
        $hold = static function (string $html) use (&$keep): string {
            $keep[] = $html;
            return "\x02" . (count($keep) - 1) . "\x03";
        };
        $text = str_replace(["\x02", "\x03"], '', $text);
        $text = (string) preg_replace_callback('/`([^`]+)`/u', static fn (array $m): string => $hold('<code>' . e($m[1]) . '</code>'), $text);
        $text = (string) preg_replace_callback('/!\[([^\]]*)\]\(berkas:(\d{1,18})\)/u', fn (array $m): string => $hold('<img src="' . $this->fileBase . $m[2] . '" alt="' . e($m[1]) . '" loading="lazy">'), $text);
        $text = (string) preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)\/][^\s)]*|berkas:\d{1,18})\)/u', function (array $m) use ($hold): string {
            $file = str_starts_with($m[2], 'berkas:');
            $url = $file ? $this->fileBase . substr($m[2], 7) : $m[2];
            return $hold('<a href="' . e($url) . '"' . ($file || str_starts_with($url, '/') ? ($file ? ' data-turbo="false"' : '') : ' target="_blank" rel="noopener noreferrer"') . '>' . e($m[1]) . '</a>');
        }, $text);
        $text = e($text);
        $text = (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);
        $text = (string) preg_replace('/(?<!\w)[*_](?!\s)(.+?)(?<!\s)[*_](?!\w)/u', '<em>$1</em>', $text);
        return (string) preg_replace_callback('/\x02(\d+)\x03/', static fn (array $m): string => $keep[(int) $m[1]] ?? '', $text);
    }
}
