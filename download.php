<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

header('Content-Type: application/json');

// Set the path to the yt-dlp binary
$ytDlpPath = __DIR__ . '/yt-dlp';
$downloadsDir = __DIR__ . '/downloads';

// GitHub link to download yt-dlp
$ytDlpUrl = 'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp';

// Function to download yt-dlp if it doesn't exist
function downloadYtDlp($url, $path) {
    if (!file_exists($path)) {
        $ch = curl_init($url);
        $fp = fopen($path, 'w+');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
        }
        curl_close($ch);
        fclose($fp);
        chmod($path, 0755); // Make it executable
    }
}

// Download yt-dlp if not present
downloadYtDlp($ytDlpUrl, $ytDlpPath);

// Get the video URL from the POST request
$videoUrl = isset($_POST['videoUrl']) ? $_POST['videoUrl'] : '';

if (empty($videoUrl)) {
    echo json_encode(['error' => 'No video URL provided.']);
    exit;
}

function emptyDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            emptyDirectory($filePath);
            rmdir($filePath);
        } else {
            unlink($filePath);
        }
    }
}

emptyDirectory($downloadsDir);

$process = new Process([$ytDlpPath, '--no-warnings', '-o', $downloadsDir . '/%(title)s.%(ext)s', $videoUrl]);
$process->run();

if (!$process->isSuccessful()) {
    echo json_encode(['error' => 'Failed to download the video.', 'details' => $process->getErrorOutput()]);
    exit;
}

$infoProcess = new Process([$ytDlpPath, '--no-warnings', '--dump-json', $videoUrl]);
$infoProcess->run();

if (!$infoProcess->isSuccessful()) {
    echo json_encode(['error' => 'Failed to retrieve video information.', 'details' => $infoProcess->getErrorOutput()]);
    exit;
}

$videoInfo = json_decode($infoProcess->getOutput(), true);

if (isset($videoInfo['url'])) {
    $downloadUrl = $videoInfo['url'];
    echo json_encode(['downloadUrl' => $downloadUrl]);
} else {
    echo json_encode(['error' => 'Failed to retrieve the download URL.']);
}
?>
