<?php
$appRoot = '/home/u989061032/domains/hitechgroup.in/public_html/hrx';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KnowledgeBase;
use Smalot\PdfParser\Parser;

$pdfs = [
    'HRX_Conversation_Bible.pdf',
    'HRX_Full_Product_Build.pdf'
];

$parser = new Parser();

echo "Starting Remote PDF Training Ingestion...\n";

foreach ($pdfs as $pdfFile) {
    $filePath = '/tmp/' . $pdfFile;
    if (!file_exists($filePath)) {
        echo "File not found in /tmp: $pdfFile\n";
        continue;
    }

    try {
        echo "Parsing $pdfFile from /tmp...\n";
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();
        
        // Clean text
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // Save to KnowledgeBase
        KnowledgeBase::updateOrCreate(
            [
                'title' => str_replace('.pdf', '', $pdfFile),
                'category' => 'Technical Specs',
                'tenant_id' => 1
            ],
            [
                'content' => $text
            ]
        );
        
        echo "Successfully ingested $pdfFile content on live server.\n";
    } catch (\Exception $e) {
        echo "Error parsing $pdfFile: " . $e->getMessage() . "\n";
    }
}

echo "Remote training complete.\n";
