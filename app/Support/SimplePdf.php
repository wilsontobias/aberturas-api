<?php

namespace App\Support;

/**
 * Generador mínimo de PDF (una o más páginas A4, texto, líneas y rectángulos).
 * No usa librerías externas: escribe el PDF a mano con las fuentes estándar
 * Helvetica y Helvetica-Bold, así que no hay nada que instalar.
 */
class SimplePdf
{
    public const WIDTH = 595;
    public const HEIGHT = 842;

    /** Ancho de cada carácter ASCII (32 a 126) de Helvetica, en milésimas de punto. */
    private const WIDTHS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var string[] contenido de cada página */
    private array $pages = [''];

    private int $current = 0;

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->current++;
    }

    /** Ancho aproximado de un texto, en puntos. */
    public function textWidth(string $text, float $size = 10, bool $bold = false): float
    {
        $width = 0;

        foreach (str_split($this->encode($text)) as $char) {
            $code = ord($char);
            $width += ($code >= 32 && $code <= 126) ? self::WIDTHS[$code - 32] : 556;
        }

        return $width * $size / 1000 * ($bold ? 1.06 : 1);
    }

    /** Escribe un texto. $y se mide desde arriba de la hoja. $align: left, right o center. */
    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, string $align = 'left'): void
    {
        if ($align !== 'left') {
            $width = $this->textWidth($text, $size, $bold);
            $x = $align === 'right' ? $x - $width : $x - $width / 2;
        }

        $this->pages[$this->current] .= sprintf(
            "BT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET\n",
            $bold ? 'F2' : 'F1',
            $size,
            $x,
            self::HEIGHT - $y,
            $this->escape($this->encode($text))
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $thickness = 0.5): void
    {
        $this->pages[$this->current] .= sprintf(
            "%.2f w %.2f %.2f m %.2f %.2f l S\n",
            $thickness,
            $x1,
            self::HEIGHT - $y1,
            $x2,
            self::HEIGHT - $y2
        );
    }

    /** Rectángulo relleno con un gris (0 = negro, 1 = blanco). */
    public function fillRect(float $x, float $y, float $width, float $height, float $gray): void
    {
        $this->pages[$this->current] .= sprintf(
            "%.2f g %.2f %.2f %.2f %.2f re f 0 g\n",
            $gray,
            $x,
            self::HEIGHT - $y - $height,
            $width,
            $height
        );
    }

    /** Devuelve el PDF completo como string binario. */
    public function output(): string
    {
        $objects = [];
        $count = count($this->pages);

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $kids = [];
        for ($i = 0; $i < $count; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $count . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->pages as $i => $content) {
            $pageId = 5 + $i * 2;
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($pageId + 1) . ' 0 R >>';
            $objects[$pageId + 1] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n" . $xref . "\n%%EOF\n";

        return $pdf;
    }

    /** Pasa de UTF-8 a Windows-1252 para que se vean las tildes y la ñ. */
    private function encode(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return $converted === false ? '' : $converted;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $text);
    }
}
