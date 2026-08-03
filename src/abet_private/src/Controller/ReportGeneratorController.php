<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// #132: migrated from src/public/report-generator/{index,generate,generateLR,view}.php
//
// Fixed here (not present before): job ownership. The legacy version had no
// job_history tracking at all -- any authenticated user could view/download
// any other user's generated report just by guessing a job id. Jobs are now
// recorded in the existing job_history table (submitted_by column), and
// view() checks ownership before serving a file.
//
// Known limitation preserved as-is (not fixed here, not a regression):
// generateLR() hardcodes year/department/degree_type rather than deriving
// them from user input. Matches legacy generateLR.php exactly -- flagging
// for a future ticket rather than guessing at what these should be.
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ReportGeneratorController extends AbstractController
{
    #[Route('/report-generator/', name: 'app_report_generator', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('report_generator/index.html.twig');
    }

    #[Route('/report-generator/generate', name: 'app_report_generator_generate', methods: ['POST'])]
    public function generate(Request $request, #[CurrentUser] User $user, Connection $connection): JsonResponse
    {
        if (!$this->isCsrfTokenValid('report_generate', (string) $request->request->get('_csrf_token'))) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token'], 403);
        }

        $file = $request->files->get('json_file');
        if (!$file) {
            return $this->json(['ok' => false, 'error' => 'No file uploaded'], 400);
        }
        if ($file->getSize() > 2 * 1024 * 1024) {
            return $this->json(['ok' => false, 'error' => 'File too large (max 2MB)'], 400);
        }
        if (strtolower((string) $file->getClientOriginalExtension()) !== 'json') {
            return $this->json(['ok' => false, 'error' => 'Only .json files are allowed'], 400);
        }

        $raw = file_get_contents($file->getPathname());
	// Strip a UTF-8 BOM if present -- json_decode() fails on it even
        // though the file is otherwise valid JSON (common export artifact).
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['ok' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
        }

        $jobsRoot = getenv('ABET_PRIVATE_DIR') . '/report_jobs';
        $jobId = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $jobDir = $jobsRoot . '/' . $jobId;
        $inputDir = $jobDir . '/input_jsons';
        $outDir = $jobDir . '/generated_pdfs';
        $logDir = $jobDir . '/logs';

        foreach ([$jobDir, $inputDir, $outDir, $logDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
                return $this->json(['ok' => false, 'error' => 'Cannot create job folder'], 500);
            }
        }

        $normalized = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($inputDir . '/input.json', $normalized);

        $generatorPath = realpath(getenv('ABET_PUBLIC_DIR') . '/cgi-bin/abetReportGenerator.py');
        $pythonBin = '/usr/bin/python3';

        if (!$generatorPath || !file_exists($generatorPath)) {
            $this->recordJob($connection, $jobId, $user->getId(), 'report_generation', 'reportgen', 'failed');
            return $this->json(['ok' => false, 'error' => 'Generator script not found'], 500);
        }

        $process = new Process([$pythonBin, $generatorPath], $jobDir, [
            'HOME' => '/tmp',
            'ABET_PRIVATE_DIR' => getenv('ABET_PRIVATE_DIR') ?: '',
            'OPENAI_API_KEY' => getenv('OPENAI_API_KEY') ?: '',
        ]);
        $process->setTimeout(120);
        $process->run();

        file_put_contents(
            $logDir . '/run.log',
            "EXIT: {$process->getExitCode()}\n\nSTDOUT:\n{$process->getOutput()}\n\nSTDERR:\n{$process->getErrorOutput()}\n"
        );

        if (!$process->isSuccessful()) {
            $this->recordJob($connection, $jobId, $user->getId(), 'report_generation', 'reportgen', 'failed');
            return $this->json(['ok' => false, 'error' => 'Generator failed', 'job_id' => $jobId], 500);
        }

        $docxFiles = glob($outDir . '/' . '*.docx');
        if (!$docxFiles) {
            $this->recordJob($connection, $jobId, $user->getId(), 'report_generation', 'reportgen', 'failed');
            return $this->json(['ok' => false, 'error' => 'Generation failed', 'job_id' => $jobId], 500);
        }
        usort($docxFiles, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $finalDocx = $outDir . '/report.docx';
        if ($docxFiles[0] !== $finalDocx) {
            rename($docxFiles[0], $finalDocx);
        }

        $pdfReady = false;
        $finalPdf = $outDir . '/report.pdf';
        $soffice = trim((string) shell_exec('command -v soffice 2>/dev/null'));
        if ($soffice !== '') {
            $convert = new Process([$soffice, '--headless', '--convert-to', 'pdf', '--outdir', $outDir, $finalDocx]);
            $convert->setTimeout(60);
            $convert->run();
            $candidatePdf = preg_replace('/\.docx$/i', '.pdf', $finalDocx);
            if (is_string($candidatePdf) && file_exists($candidatePdf)) {
                if ($candidatePdf !== $finalPdf) {
                    rename($candidatePdf, $finalPdf);
                }
                $pdfReady = file_exists($finalPdf);
            }
        }

        $this->recordJob($connection, $jobId, $user->getId(), 'report_generation', 'reportgen', 'completed');

        return $this->json([
            'ok' => true,
            'job_id' => $jobId,
            'pdf_ready' => $pdfReady,
            'pdf_url' => $pdfReady ? $this->generateUrl('app_report_generator_view', ['job' => $jobId, 'file' => 'report.pdf']) : null,
            'docx_url' => $this->generateUrl('app_report_generator_view', ['job' => $jobId, 'file' => 'report.docx']),
        ]);
    }

    #[Route('/report-generator/generateLR', name: 'app_report_generator_generate_lr', methods: ['POST'])]
    public function generateLR(Request $request, #[CurrentUser] User $user, Connection $connection): JsonResponse
    {
        if (!$this->isCsrfTokenValid('report_generate', (string) $request->request->get('_csrf_token'))) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token'], 403);
        }

        $jobsRoot = getenv('ABET_PRIVATE_DIR') . '/report_jobs';
        $jobId = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $jobDir = $jobsRoot . '/' . $jobId;
        $outDir = $jobDir . '/generated_pdfs';
        $logDir = $jobDir . '/logs';
        foreach ([$jobDir, $logDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
                return $this->json(['ok' => false, 'error' => 'Cannot create job folder'], 500);
            }
        }

        // Known limitation, preserved from legacy generateLR.php: these
        // values are hardcoded rather than derived from user input.
        $host = getenv('REPORTGEN_HOSTNAME') ?: 'reportgen';
        $port = getenv('REPORTGEN_PORT') ?: '8002';
        $url = "http://{$host}:{$port}/generate-report";
        $payload = json_encode(['year' => 2026, 'department' => 'CSE', 'degree_type' => 'BS']);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        file_put_contents($logDir . '/run.log', "URL: {$url}\nHTTP: {$httpCode}\nERR: {$error}\n");

        if ($error || $httpCode < 200 || $httpCode >= 300) {
            $this->recordJob($connection, $jobId, $user->getId(), 'long_report_generation', 'reportgen', 'failed');
            return $this->json(['ok' => false, 'error' => 'Report API error: ' . ($error ?: "HTTP {$httpCode}"), 'job_id' => $jobId], 500);
        }

        if (!is_dir($outDir) && !mkdir($outDir, 0700, true)) {
            return $this->json(['ok' => false, 'error' => 'Cannot create output directory'], 500);
        }
        $finalDocx = $outDir . '/report.docx';
        file_put_contents($finalDocx, $body);

        $this->recordJob($connection, $jobId, $user->getId(), 'long_report_generation', 'reportgen', 'completed');

        return $this->json([
            'ok' => true,
            'job_id' => $jobId,
            'docx_url' => $this->generateUrl('app_report_generator_view', ['job' => $jobId, 'file' => 'report.docx']),
        ]);
    }

    #[Route('/report-generator/view', name: 'app_report_generator_view', methods: ['GET'])]
    public function view(Request $request, #[CurrentUser] User $user, Connection $connection): Response
    {
        $job = (string) $request->query->get('job', '');
        $file = (string) $request->query->get('file', '');

        if (!preg_match('/^[A-Za-z0-9_-]{10,80}$/', $job)) {
            throw $this->createNotFoundException('Invalid job id');
        }
        if (!in_array($file, ['report.pdf', 'report.docx'], true)) {
            throw $this->createNotFoundException('Invalid file');
        }

        // #132: ownership check -- this did not exist in the legacy version.
        $owner = $connection->fetchOne(
            'SELECT submitted_by FROM job_history WHERE id = :id',
            ['id' => $job]
        );
        if ($owner === false) {
            throw $this->createNotFoundException('Job not found');
        }
        if ((int) $owner !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You do not have permission to access this report.');
        }

        $base = getenv('ABET_PRIVATE_DIR') . '/report_jobs/' . $job . '/generated_pdfs/';
        $path = realpath($base . $file);
        $baseReal = realpath($base);
        if (!$path || !$baseReal || !str_starts_with($path, $baseReal) || !is_file($path)) {
            throw $this->createNotFoundException('File not found');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($file === 'report.pdf') {
            $response->headers->set('Content-Type', 'application/pdf');
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'report.pdf');
        } else {
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'report.docx');
        }

        return $response;
    }

    private function recordJob(Connection $connection, string $jobId, int $userId, string $jobType, string $service, string $status): void
    {
        try {
            $connection->executeStatement(
                'INSERT INTO job_history (id, job_type, service, submitted_by, status, completed_at)
                 VALUES (:id, :job_type, :service, :submitted_by, :status, NOW())',
                [
                    'id' => $jobId,
                    'job_type' => $jobType,
                    'service' => $service,
                    'submitted_by' => $userId,
                    'status' => $status,
                ]
            );
        } catch (\Throwable $e) {
            // Don't block the response on logging failure.
        }
    }
}
