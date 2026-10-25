<?php

namespace App\Domains\Og\Services;

use GdImage;

/**
 * Картинка-превью 1200×630 для ссылок в Discord и Telegram.
 *
 * Рисуется через GD: тёмный фон, фиолетовая полоса бренда, крупный
 * заголовок и «блок» тира или тега клана справа — как на сайте.
 * Шрифт DejaVu лежит в resources/fonts, чтобы не зависеть от системы.
 */
class OgImageRenderer
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const BG = '#0d0c11';

    private const CARD = '#16141c';

    private const ACCENT = '#7c3aed';

    private const ACCENT_EDGE = '#4c1d95';

    private const ACCENT_LIGHT = '#a78bfa';

    private const TEXT = '#ecebf1';

    private const MUTED = '#8b8599';

    /**
     * @param  array{
     *     title: string,
     *     lines: array<int, array{text: string, color?: string}>,
     *     badge: string,
     *     badge_color: string,
     *     footer: string,
     * }  $card
     */
    public function render(array $card): string
    {
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($img, true);

        imagefilledrectangle($img, 0, 0, self::WIDTH, self::HEIGHT, $this->color($img, self::BG));
        imagefilledrectangle($img, 40, 40, self::WIDTH - 40, self::HEIGHT - 40, $this->color($img, self::CARD));

        // Полоса бренда слева с «гранью» блока
        imagefilledrectangle($img, 40, 40, 56, self::HEIGHT - 40, $this->color($img, self::ACCENT));
        imagefilledrectangle($img, 52, 40, 56, self::HEIGHT - 40, $this->color($img, self::ACCENT_EDGE));

        $this->text($img, 'APEX TIERS', 26, 100, 112, self::ACCENT_LIGHT, true);

        // Заголовок ужимаем, пока не влезет в левую часть
        $size = 76;
        while ($size > 36 && $this->width($card['title'], $size, true) > 700) {
            $size -= 4;
        }
        $this->text($img, $this->fit($card['title'], $size, 700, true), $size, 100, 290, self::TEXT, true);

        $y = 370;
        foreach (array_slice($card['lines'], 0, 3) as $line) {
            $this->text($img, $this->fit($line['text'], 30, 700), 30, 100, $y, $line['color'] ?? self::MUTED);
            $y += 52;
        }

        $this->text($img, $card['footer'], 22, 100, self::HEIGHT - 80, self::MUTED);

        $this->block($img, $card['badge'], $card['badge_color']);

        ob_start();
        imagepng($img, null, 6);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    /** Квадратный «блок» с тёмной нижней гранью — тир или тег клана. */
    private function block(GdImage $img, string $label, string $hex): void
    {
        $size = 240;
        $x = self::WIDTH - 100 - $size;
        $y = (int) ((self::HEIGHT - $size) / 2);

        [$r, $g, $b] = $this->rgb($hex);
        $edge = imagecolorallocate($img, (int) ($r * 0.62), (int) ($g * 0.62), (int) ($b * 0.62));

        imagefilledrectangle($img, $x, $y, $x + $size, $y + $size, $edge);
        imagefilledrectangle($img, $x, $y, $x + $size, $y + $size - 18, $this->color($img, $hex));

        // Длинный тег уменьшаем, пока не влезет в блок с полями
        $fontSize = 130;
        while ($fontSize > 20 && $this->width($label, $fontSize, true) > $size - 48) {
            $fontSize -= 4;
        }

        // На тёмном цвете — светлый текст, на светлом — тёмный
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $ink = $luminance > 0.55 ? '#15121c' : '#ffffff';

        $box = imagettfbbox($fontSize, 0, $this->font(true), $label);
        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];

        $this->text(
            $img,
            $label,
            $fontSize,
            $x + (int) (($size - $textWidth) / 2) - $box[0],
            $y + (int) (($size - 18 + $textHeight) / 2),
            $ink,
            true,
        );
    }

    private function text(GdImage $img, string $text, int $size, int $x, int $y, string $hex, bool $bold = false): void
    {
        imagettftext($img, $size, 0, $x, $y, $this->color($img, $hex), $this->font($bold), $text);
    }

    private function width(string $text, int $size, bool $bold = false): int
    {
        $box = imagettfbbox($size, 0, $this->font($bold), $text);

        return $box[2] - $box[0];
    }

    /** Обрезать строку с многоточием под ширину. */
    private function fit(string $text, int $size, int $max, bool $bold = false): string
    {
        if ($this->width($text, $size, $bold) <= $max) {
            return $text;
        }

        while (mb_strlen($text) > 1 && $this->width($text.'…', $size, $bold) > $max) {
            $text = mb_substr($text, 0, -1);
        }

        return $text.'…';
    }

    private function font(bool $bold): string
    {
        return resource_path($bold ? 'fonts/DejaVuSans-Bold.ttf' : 'fonts/DejaVuSans.ttf');
    }

    private function color(GdImage $img, string $hex): int
    {
        [$r, $g, $b] = $this->rgb($hex);

        return imagecolorallocate($img, $r, $g, $b);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $hex = ltrim(self::ACCENT, '#');
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
