<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use mikehaertl\wkhtmlto\Pdf;

$app->post('/api/generate-pdf', function (Request $request, Response $response) use ($app) {
    try {
        // Lấy dữ liệu từ body$request

        $data = $request->getBody()->getContents();
        $data = json_decode($data, true);

        // Debug: Ghi log dữ liệu nhận được
        var_dump('Received data: ' . print_r($data, true));

        // Kiểm tra dữ liệu
        if (empty($data) || !is_array($data)) {
            throw new Exception('Invalid or empty data provided');
        }

        // Render template với dữ liệu
        $twig = $app->getContainer()->get('twig');
        $html = $twig->render('page.html.twig', ['data' => $data]);

        // Tạo file HTML tạm
        $tempHtmlFile = __DIR__ . '/../public/storage/temp_' . uniqid() . '.html';
        if (!file_put_contents($tempHtmlFile, $html)) {
            throw new Exception('Failed to create temporary HTML file');
        }
        $htmlUrl = $_ENV['APP_URL'] . '/storage/' . basename($tempHtmlFile);

        // Khởi tạo wkhtmltopdf
        $pdf = new Pdf([
            'binary' => $_ENV['WKHTMLTOPDF_PATH'],
            'commandOptions' => [
                'enableLocalFileAccess' => true
            ]
        ]);

        $pdf->addPage($htmlUrl);

        // Tạo tên file PDF
        $pdfFileName = 'output_' . uniqid() . '.pdf';
        $pdfFilePath = __DIR__  . $_ENV['STORAGE_PATH'] . '/' . $pdfFileName;

        // Lưu file PDF
        if (!$pdf->saveAs($pdfFilePath)) {
            throw new Exception($pdf->getError() ?: 'Failed to generate PDF');
        }

        // Xóa file HTML tạm
        if (file_exists($tempHtmlFile)) {
            unlink($tempHtmlFile);
        }

        // Trả về link PDF
        $pdfUrl = $_ENV['APP_URL'] . '/storage/' . $pdfFileName;
        $response->getBody()->write(json_encode(['pdf_url' => $pdfUrl]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
        // Debug: Ghi log lỗi
        error_log('Error: ' . $e);
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});
