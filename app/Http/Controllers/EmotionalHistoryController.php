<?php

namespace App\Http\Controllers;

use App\Models\EmotionalHistory;
use Illuminate\Http\Request;

class EmotionalHistoryController extends Controller
{
    public function index(Request $request)
    {
        $entries = EmotionalHistory::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($entries);
    }

    public function store(Request $request)
    {
        $request->validate([
            'companion' => 'nullable|string',
            'recognized_emotion' => 'nullable|string',
            'depression_score' => 'nullable|integer|min:0|max:42|required_with:anxiety_score,stress_score',
            'anxiety_score' => 'nullable|integer|min:0|max:42|required_with:depression_score,stress_score',
            'stress_score' => 'nullable|integer|min:0|max:42|required_with:depression_score,anxiety_score',
        ]);

        if (! $request->filled('recognized_emotion') && ! $request->filled('depression_score')) {
            return response()->json([
                'message' => 'Debes enviar una emocion reconocida o resultados DASS',
            ], 422);
        }

        $entry = EmotionalHistory::create([
            'user_id' => $request->user()->id,
            'companion' => $request->companion ?? 'emotion-model',
            'recognized_emotion' => $request->filled('recognized_emotion') ? strtolower($request->recognized_emotion) : null,
            'depression_score' => $request->depression_score,
            'anxiety_score' => $request->anxiety_score,
            'stress_score' => $request->stress_score,
            'depression_severity' => $request->depression_score !== null
                ? $this->severityFor($request->depression_score, 'depression')
                : null,
            'anxiety_severity' => $request->anxiety_score !== null
                ? $this->severityFor($request->anxiety_score, 'anxiety')
                : null,
            'stress_severity' => $request->stress_score !== null
                ? $this->severityFor($request->stress_score, 'stress')
                : null,
        ]);

        return response()->json($entry, 201);
    }

    private function severityFor(int $score, string $category): string
    {
        $thresholds = [
            'depression' => [28, 21, 14, 10],
            'anxiety' => [20, 15, 10, 8],
            'stress' => [34, 26, 19, 15],
        ][$category];

        return match (true) {
            $score >= $thresholds[0] => 'Intensidad muy alta',
            $score >= $thresholds[1] => 'Intensidad alta',
            $score >= $thresholds[2] => 'Intensidad moderada',
            $score >= $thresholds[3] => 'Intensidad leve',
            default => 'Rango esperado',
        };
    }

    public function destroy(Request $request, EmotionalHistory $emotionalHistory)
    {
        if ($emotionalHistory->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $emotionalHistory->delete();

        return response()->json(['message' => 'Entrada eliminada']);
    }

    public function exportPdf(Request $request)
    {
        $entries = EmotionalHistory::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $pdf = $this->buildEmotionalHistoryPdf($request->user(), $entries);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="emotional-history.pdf"',
        ]);
    }

    private function buildEmotionalHistoryPdf($user, $entries): string
    {
        $pages = [];
        $content = $this->startDesignedPage();
        $y = 745;

        $content .= $this->pdfText('EMORIA', 52, $y, 26, 'F2', [39, 55, 77]);
        $content .= $this->pdfText('Historial emocional', 52, $y - 26, 16, 'F2', [56, 95, 91]);
        $content .= $this->pdfText('Un resumen pensado para observar tu proceso con calma y amabilidad.', 52, $y - 48, 10, 'F1', [86, 100, 119]);
        $content .= $this->pdfText('Usuario: '.$user->username, 390, $y - 2, 10, 'F1', [86, 100, 119]);
        $content .= $this->pdfText('Generado: '.now()->format('Y-m-d H:i'), 390, $y - 18, 10, 'F1', [86, 100, 119]);

        $summary = $this->emotionalSummary($entries);
        $content .= $this->roundedCard(52, 610, 508, 86, [238, 247, 244], [179, 219, 210]);
        $content .= $this->pdfText('Resumen de bienestar', 72, 670, 14, 'F2', [39, 55, 77]);
        $content .= $this->pdfText('Registros: '.$entries->count(), 72, 646, 11, 'F2', [56, 95, 91]);
        $content .= $this->pdfText('Promedio DASS: '.$summary['average_score'], 195, 646, 11, 'F2', [56, 95, 91]);
        $content .= $this->pdfText('Ultima emocion: '.$summary['last_emotion'], 345, 646, 11, 'F2', [56, 95, 91]);
        $content .= $this->pdfText('Este documento no te define: solo ayuda a notar patrones y pedir apoyo a tiempo.', 72, 624, 10, 'F1', [86, 100, 119]);

        $content .= $this->sectionTitle('Acompanamiento emocional', 52, 568);
        $content .= $this->paragraph(
            'Tus resultados son una fotografia de un momento. Si aparecen puntajes altos o emociones dificiles, intenta leerlos como senales de cuidado, no como fallas personales. Compartir este reporte con alguien de confianza o con un profesional puede ayudarte a ordenar lo que sientes.',
            52,
            545,
            92,
            11,
            [56, 65, 82]
        );

        $y = 455;
        $content .= $this->sectionTitle('Registros recientes', 52, $y);
        $y -= 28;

        if ($entries->isEmpty()) {
            $content .= $this->emptyState(52, $y - 72);
        }

        foreach ($entries as $entry) {
            if ($y < 170) {
                $pages[] = $content.$this->pageFooter(count($pages) + 1);
                $content = $this->startDesignedPage();
                $content .= $this->pdfText('EMORIA - Historial emocional', 52, 785, 14, 'F2', [39, 55, 77]);
                $content .= $this->pdfText('Continuacion de registros', 52, 766, 10, 'F1', [86, 100, 119]);
                $y = 715;
            }

            $content .= $this->entryCard($entry, 52, $y);
            $y -= 150;
        }

        $pages[] = $content.$this->pageFooter(count($pages) + 1);

        return $this->buildPdfFromPages($pages);
    }

    private function startDesignedPage(): string
    {
        $content = $this->rect(0, 0, 612, 842, [250, 252, 251]);
        $content .= $this->rect(0, 790, 612, 52, [218, 238, 232]);
        $content .= $this->rect(0, 0, 612, 36, [39, 55, 77]);
        $content .= $this->circle(535, 742, 42, [232, 244, 241]);
        $content .= $this->circle(568, 704, 22, [247, 232, 222]);

        return $content;
    }

    private function emotionalSummary($entries): array
    {
        if ($entries->isEmpty()) {
            return [
                'average_score' => 'Sin datos',
                'last_emotion' => 'Sin registro',
            ];
        }

        $dassEntries = $entries->filter(fn ($entry) => $this->hasDassScores($entry));
        $average = $dassEntries->isEmpty()
            ? 'Sin datos DASS'
            : round($dassEntries->avg(fn ($entry) => $entry->depression_score + $entry->anxiety_score + $entry->stress_score), 1);

        return [
            'average_score' => $average,
            'last_emotion' => $entries->first()->recognized_emotion ?: 'No registrada',
        ];
    }

    private function entryCard(EmotionalHistory $entry, int $x, int $y): string
    {
        $content = $this->roundedCard($x, $y - 120, 508, 126, [255, 255, 255], [223, 230, 236]);
        $content .= $this->pdfText($entry->created_at->format('Y-m-d H:i'), $x + 18, $y - 20, 10, 'F2', [56, 95, 91]);
        $content .= $this->pdfText('Companion: '.$entry->companion, $x + 150, $y - 20, 10, 'F1', [86, 100, 119]);
        $content .= $this->pdfText('Emocion reconocida: '.($entry->recognized_emotion ?? 'N/A'), $x + 318, $y - 20, 10, 'F1', [86, 100, 119]);

        if (! $this->hasDassScores($entry)) {
            $content .= $this->pdfText('Registro generado por el modelo de reconocimiento emocional.', $x + 18, $y - 56, 11, 'F2', [39, 55, 77]);
            $content .= $this->pdfText('Este dato muestra la emocion detectada para el usuario en ese momento.', $x + 18, $y - 78, 10, 'F1', [86, 100, 119]);
            $content .= $this->pdfText('Usalo como una senal suave para acompanar, no como diagnostico.', $x + 18, $y - 98, 10, 'F1', [86, 100, 119]);

            return $content;
        }

        $content .= $this->metricRow('Depresion', $entry->depression_score, $entry->depression_severity, $x + 18, $y - 52, [93, 135, 117]);
        $content .= $this->metricRow('Ansiedad', $entry->anxiety_score, $entry->anxiety_severity, $x + 18, $y - 78, [84, 112, 168]);
        $content .= $this->metricRow('Estres', $entry->stress_score, $entry->stress_severity, $x + 18, $y - 104, [183, 116, 84]);

        $message = $this->supportMessage($entry);
        $content .= $this->pdfText($message, $x + 300, $y - 76, 9, 'F1', [86, 100, 119]);

        return $content;
    }

    private function hasDassScores(EmotionalHistory $entry): bool
    {
        return $entry->depression_score !== null
            && $entry->anxiety_score !== null
            && $entry->stress_score !== null;
    }

    private function metricRow(string $label, int $score, string $severity, int $x, int $y, array $color): string
    {
        $maxDassMetricScore = 42;
        $barWidth = 145;
        $filledWidth = min($barWidth, max(0, ($score / $maxDassMetricScore) * $barWidth));

        $content = $this->pdfText($label, $x, $y, 9, 'F2', [39, 55, 77]);
        $content .= $this->rect($x + 72, $y - 4, $barWidth, 7, [231, 236, 240]);
        $content .= $this->rect($x + 72, $y - 4, $filledWidth, 7, $color);
        $content .= $this->pdfText($score.' - '.$severity, $x + 230, $y, 9, 'F1', [86, 100, 119]);

        return $content;
    }

    private function supportMessage(EmotionalHistory $entry): string
    {
        $severities = [
            $entry->depression_severity,
            $entry->anxiety_severity,
            $entry->stress_severity,
        ];

        if (in_array('Intensidad muy alta', $severities, true)
            || in_array('Intensidad alta', $severities, true)) {
            return 'Puede ser util conversar con un profesional.';
        }

        if (in_array('Intensidad moderada', $severities, true)) {
            return 'Observa esta senal y considera compartir como te sientes.';
        }

        return 'Este registro no reemplaza una evaluacion profesional.';
    }

    private function sectionTitle(string $text, int $x, int $y): string
    {
        return $this->pdfText($text, $x, $y, 14, 'F2', [39, 55, 77])
            .$this->rect($x, $y - 10, 42, 3, [97, 160, 143]);
    }

    private function paragraph(string $text, int $x, int $y, int $charsPerLine, int $size, array $color): string
    {
        $content = '';
        $lineY = $y;

        foreach (explode("\n", wordwrap($this->sanitizePdfText($text), $charsPerLine)) as $line) {
            $content .= $this->pdfText($line, $x, $lineY, $size, 'F1', $color);
            $lineY -= $size + 5;
        }

        return $content;
    }

    private function emptyState(int $x, int $y): string
    {
        $content = $this->roundedCard($x, $y, 508, 88, [255, 255, 255], [223, 230, 236]);
        $content .= $this->pdfText('Todavia no hay registros emocionales', $x + 22, $y + 52, 13, 'F2', [39, 55, 77]);
        $content .= $this->pdfText('Cuando completes una evaluacion, aqui aparecera un resumen claro y amable de tu proceso.', $x + 22, $y + 30, 10, 'F1', [86, 100, 119]);

        return $content;
    }

    private function pageFooter(int $page): string
    {
        return $this->pdfText('EMORIA acompana tu bienestar emocional. Si estas en riesgo inmediato, contacta a emergencias o a una persona de confianza.', 52, 20, 8, 'F1', [238, 247, 244])
            .$this->pdfText('Pagina '.$page, 540, 20, 8, 'F1', [238, 247, 244]);
    }

    private function buildPdfFromPages(array $pages): string
    {
        $objects = [
            1 => "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        ];
        $pageObjectIds = [];
        $fontObjectId = 3;
        $boldFontObjectId = 4;
        $nextObjectId = 5;

        foreach ($pages as $pageContent) {
            $pageObjectId = $nextObjectId++;
            $contentObjectId = $nextObjectId++;
            $pageObjectIds[] = $pageObjectId;

            $objects[$pageObjectId] = "{$pageObjectId} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 {$fontObjectId} 0 R /F2 {$boldFontObjectId} 0 R >> >> /Contents {$contentObjectId} 0 R >>\nendobj\n";
            $objects[$contentObjectId] = "{$contentObjectId} 0 obj\n<< /Length ".strlen($pageContent)." >>\nstream\n{$pageContent}endstream\nendobj\n";
        }

        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [".implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageObjectIds)).'] /Count '.count($pageObjectIds)." >>\nendobj\n";
        $objects[$fontObjectId] = "{$fontObjectId} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $objects[$boldFontObjectId] = "{$boldFontObjectId} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $maxObjectId = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxObjectId + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObjectId; $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".($maxObjectId + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $text, float $x, float $y, int $size, string $font, array $color): string
    {
        [$r, $g, $b] = $this->rgb($color);
        $text = $this->escapePdfText($this->sanitizePdfText($text));

        return "BT\n{$r} {$g} {$b} rg\n/{$font} {$size} Tf\n1 0 0 1 {$x} {$y} Tm\n({$text}) Tj\nET\n";
    }

    private function rect(float $x, float $y, float $width, float $height, array $fill): string
    {
        [$r, $g, $b] = $this->rgb($fill);

        return "{$r} {$g} {$b} rg\n{$x} {$y} {$width} {$height} re\nf\n";
    }

    private function roundedCard(float $x, float $y, float $width, float $height, array $fill, array $stroke): string
    {
        [$fr, $fg, $fb] = $this->rgb($fill);
        [$sr, $sg, $sb] = $this->rgb($stroke);

        return "{$fr} {$fg} {$fb} rg\n{$x} {$y} {$width} {$height} re\nf\n{$sr} {$sg} {$sb} RG\n0.8 w\n{$x} {$y} {$width} {$height} re\nS\n";
    }

    private function circle(float $x, float $y, float $radius, array $fill): string
    {
        [$r, $g, $b] = $this->rgb($fill);
        $c = $radius * 0.55228475;

        return "{$r} {$g} {$b} rg\n"
            .($x + $radius)." {$y} m\n"
            .($x + $radius).' '.($y + $c).' '.($x + $c).' '.($y + $radius)." {$x} ".($y + $radius)." c\n"
            .($x - $c).' '.($y + $radius).' '.($x - $radius).' '.($y + $c).' '.($x - $radius)." {$y} c\n"
            .($x - $radius).' '.($y - $c).' '.($x - $c).' '.($y - $radius)." {$x} ".($y - $radius)." c\n"
            .($x + $c).' '.($y - $radius).' '.($x + $radius).' '.($y - $c).' '.($x + $radius)." {$y} c\n"
            ."f\n";
    }

    private function rgb(array $color): array
    {
        return array_map(fn ($value) => round($value / 255, 3), $color);
    }

    private function sanitizePdfText(string $text): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
