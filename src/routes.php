<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use mikehaertl\wkhtmlto\Pdf;
use App\Models\PdfFile;

/**
 * Vệ sinh dữ liệu đầu vào để tránh XSS
 * @param array $data
 * @return array
 */
function sanitizeInput(array $data): array
{
    return array_map(function ($value) {
        return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
    }, $data);
}

/**
 * Tạo file HTML tạm và trả về URL
 * @param string $html
 * @param string $storagePath
 * @param string $appUrl
 * @return string
 * @throws Exception
 */
function createTempHtmlFile(string $html, string $storagePath, string $appUrl): string
{
    $tempHtmlFile = $storagePath . '/temp_' . uniqid() . '.html';
    if (!file_put_contents($tempHtmlFile, $html)) {
        throw new Exception('Failed to create temporary HTML file');
    }
    return $appUrl . '/storage/' . basename($tempHtmlFile);
}

/**
 * Tạo file PDF và trả về đường dẫn
 * @param string $htmlUrl
 * @param string $storagePath
 * @param string $wkhtmltopdfPath
 * @return string
 * @throws Exception
 */
function generatePdf(string $htmlUrl, string $storagePath, string $wkhtmltopdfPath): array
{
    if (!file_exists($wkhtmltopdfPath)) {
        throw new Exception('wkhtmltopdf binary not found at: ' . $wkhtmltopdfPath);
    };

    $pdf = new Pdf([
        'binary' => $wkhtmltopdfPath
    ]);
    $pdf->addPage($htmlUrl);

    $pdfFileName = 'output_' . uniqid() . '.pdf';
    $pdfFilePath = $storagePath . '/' . $pdfFileName;

    if (!$pdf->saveAs($pdfFilePath)) {
        throw new Exception($pdf->getError() ?: 'Failed to generate PDF');
    };

    return [
        'file_name' => $pdfFileName,
        'file_path' => $pdfFilePath
    ];
}

/**
 * Xóa file cũ trong thư mục storage (> 1 ngày)
 * @param string $storagePath
 */
function cleanOldFiles(string $storagePath): void
{
    $files = glob($storagePath . '/*.{html}', GLOB_BRACE);
    foreach ($files as $file) {
        if (filemtime($file) < time() - 86400) { // 1 ngày
            @unlink($file);
        }
    }
}

$app->post('/api/generate-pdf', function (Request $request, Response $response) use ($app) {
    try {
        // Lấy dữ liệu từ body
        $data = $request->getBody()->getContents();
         $data = json_decode($data, true);
        if (empty($data) || !is_array($data)) {
            throw new Exception('Invalid or empty data provided');
        }

        // Vệ sinh dữ liệu
        $data = sanitizeInput($data);

        // Render template với dữ liệu
        $twig = $app->getContainer()->get('twig');
        if (!$twig->getLoader()->exists('page.html.twig')) {
            throw new Exception('Template page.html.twig not found');
        }
        $html = $twig->render('page.html.twig', ['data' => $data]);

        // Đường dẫn storage
        $storagePath = __DIR__ . '/../' . $_ENV['STORAGE_PATH'];
        if (!is_dir($storagePath) || !is_writable($storagePath)) {
            throw new Exception('Storage directory is not accessible or writable');
        }

        // Xóa file cũ
        cleanOldFiles($storagePath);

        // Tạo file HTML tạm
        $htmlUrl = createTempHtmlFile($html, $storagePath, $_ENV['APP_URL']);

        // Tạo file PDF
        $generatePdf = generatePdf($htmlUrl, $storagePath, $_ENV['WKHTMLTOPDF_PATH']);


        // Lưu metadata vào database
        $pdfRecord = PdfFile::create([
            'file_name' => $generatePdf['file_name'],
            'file_path' => $generatePdf['file_path'],
        ]);

        // Xóa file HTML tạm
        $tempHtmlFile = $storagePath . '/' . basename($htmlUrl);
        if (file_exists($tempHtmlFile)) {
            @unlink($tempHtmlFile);
        }

        // Trả về link PDF
        $pdfUrl = $_ENV['APP_URL'] . '/storage/' . $pdfFileName;
        $response->getBody()->write(json_encode(['pdf_url' => $pdfUrl]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
        // Ghi log lỗi với chi tiết
        error_log('Error in /api/generate-pdf: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
}
);