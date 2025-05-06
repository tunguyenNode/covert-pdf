<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use mikehaertl\wkhtmlto\Pdf;

function renderPage (Request $request, Response $response){
    $data = $request->getBody()->getContents();
    $data = json_decode($data, true);
    if (empty($data) || !is_array($data)) {
        throw new Exception('Invalid or empty data provided');
    }

    // Render template với dữ liệu
    $twig = $app->getContainer()->get('twig');
    $html = $twig->render('page.html.twig', ['data' => $data]);

    // Lưu file HTML vào storage
    $htmlFileName = 'page_' . uniqid() . '.html';
    $htmlFilePath = __DIR__ . '/../' . $_ENV['STORAGE_PATH'] . '/' . $htmlFileName;
    if (!file_put_contents($htmlFilePath, $html)) {
        throw new Exception('Failed to create HTML file');
    }

    // Trả về URL của file HTML
    $htmlUrl = $_ENV['APP_URL'] . '/storage/' . $htmlFileName;
    return htmlUrl;
}

$app->post('/api/render-page', function (Request $request, Response $response) use ($app) {
    try {
        $htmlUrl = renderPage($request, $response);
        $response->getBody()->write(json_encode(['html_url' => $htmlUrl]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
        error_log('Error in /api/render-page: ' . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});



$app->post('/api/generate-pdf', function (Request $request, Response $response) use ($app) {
    try {
        // Lấy html_url từ body
        $data = $request->getBody()->getContents();
        // create Page
        $htmlUrl = renderPage($request,  $response);

        // Khởi tạo wkhtmltopdf
        $pdf = new Pdf([
            'binary' => $_ENV['WKHTMLTOPDF_PATH']
        ]);
        $pdf->addPage($htmlUrl);

        // Tạo tên file PDF
        $pdfFileName = 'output_' . uniqid() . '.pdf';
        $pdfFilePath = __DIR__ . '/../' . $_ENV['STORAGE_PATH'] . '/' . $pdfFileName;

        // Lưu file PDF
        if (!$pdf->saveAs($pdfFilePath)) {
            throw new Exception($pdf->getError() ?: 'Failed to generate PDF');
        }

        // Trả về link PDF
        $pdfUrl = $_ENV['APP_URL'] . '/storage/' . $pdfFileName;
        $response->getBody()->write(json_encode(['pdf_url' => $pdfUrl]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    } catch (Exception $e) {
        error_log('Error in /api/generate-pdf: ' . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

