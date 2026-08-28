<?php
import('classes.handler.Handler');

class GeminiReviewerHandler extends Handler {

    public function authorize($request, &$args, $roleAssignments) {
        return true;
    }

    private function getEnvVariable($key, $default = null) {
        $envPath = Core::getBaseDir() . DIRECTORY_SEPARATOR . '.env';
        if (!file_exists($envPath)) {
            $envPath = dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . '.env';
        }

        if (file_exists($envPath) && is_readable($envPath)) {
            $lines = file($envPath, 6);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || substr($line, 0, 1) === '#') continue;
                if (strpos($line, '=') !== false) {
                    $parts = explode('=', $line, 2);
                    if (trim($parts[0]) === $key) {
                        return trim($parts[1], "\"' \t\n\r");
                    }
                }
            }
        }
        $sysEnv = getenv($key);
        return ($sysEnv !== false) ? $sysEnv : $default;
    }

    private function buildFreshDocx($title, $reviewText) {
        if (!class_exists('ZipArchive')) return null;

        $tempDocx = tempnam(sys_get_temp_dir(), 'rev_') . '.docx';
        $zip = new ZipArchive();
        if ($zip->open($tempDocx, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>' .
            '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>' .
            '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        $lines = explode("\n", htmlspecialchars($reviewText, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        $bodyXml = '';
        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t)) {
                $bodyXml .= '<w:p/>';
                continue;
            }
            $bodyXml .= '<w:p><w:r><w:rPr><w:sz w:val="22"/></w:rPr><w:t>' . $t . '</w:t></w:r></w:p>';
        }

        $docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' .
            '<w:body>' . $bodyXml . '</w:body>' .
            '</w:document>';
        $zip->addFromString('word/document.xml', $docXml);
        $zip->close();

        return $tempDocx;
    }

    public function generateReview($args, $request) {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');

        $apiKey = $this->getEnvVariable('GEMINI_API_KEY');
        $model  = $this->getEnvVariable('GEMINI_MODEL', 'gemini-3.6-flash');

        if (empty($apiKey)) {
            echo json_encode(array('status' => false, 'message' => 'GEMINI_API_KEY tidak ditemukan di .env.'));
            exit;
        }

        $context = $request->getContext();
        $contextId = $context ? $context->getId() : CONTEXT_ID_NONE;
        
        $submissionId = (int) $request->getUserVar('submissionId');
        $reviewAssignmentId = (int) $request->getUserVar('reviewAssignmentId');

        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($submissionId, $contextId);

        if (!$submission) {
            echo json_encode(array('status' => false, 'message' => 'Naskah tidak ditemukan.'));
            exit;
        }

        $publication = $submission->getCurrentPublication();
        $title = (string) ($publication ? $publication->getLocalizedTitle() : $submission->getLocalizedTitle());
        $abstract = (string) strip_tags($publication ? $publication->getLocalizedData('abstract') : $submission->getLocalizedAbstract());

        $plugin = PluginRegistry::getPlugin('generic', 'geminireviewerplugin');
        $customPrompt = "";
        if ($plugin) {
            $customPrompt = (string) $plugin->getSetting($contextId, 'customPrompt');
        }

        // Strict system instruction to eliminate conversational preambles and metadata headers
        $systemInstruction = "You are an expert double-blind academic peer reviewer for a reputable scientific journal.\n";
        $systemInstruction .= "CRITICAL FORMATTING INSTRUCTION: Do NOT include any introductory greetings, meta-announcements, journal title headers, manuscript metadata headers, or conversational preambles (e.g., do NOT write 'Here is a comprehensive evaluation...', 'EXPERT PEER REVIEW REPORT', or metadata lines). Start immediately and directly with '### 1. Contribution & Novelty'.\n\n";
        
        if (!empty($customPrompt)) {
            $systemInstruction .= "Evaluation Guidelines:\n" . $customPrompt;
        } else {
            $systemInstruction .= "Evaluate the manuscript thoroughly based on: 1) Contribution & Novelty, 2) Methodological Soundness, 3) Logical Flow & Organization, 4) Critical Flaws & Weaknesses, 5) Concrete Suggestions for Improvement, and 6) Verdict Recommendation (Accept, Minor Revisions, Major Revisions, or Decline).";
        }

        $promptText = "TITLE:\n" . $title . "\n\nABSTRACT:\n" . $abstract;

        $postData = array(
            'contents' => array(
                array(
                    'role' => 'user',
                    'parts' => array(
                        array('text' => $systemInstruction . "\n\n" . $promptText)
                    )
                )
            )
        );

        $cleanModel = str_replace('models/', '', $model);
        $genUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $cleanModel . ':generateContent?key=' . $apiKey;

        $chGen = curl_init($genUrl);
        curl_setopt($chGen, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chGen, CURLOPT_POST, true);
        curl_setopt($chGen, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($chGen, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($chGen, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($chGen, CURLOPT_TIMEOUT, 60);
        $response = curl_exec($chGen);
        $httpCode = curl_getinfo($chGen, CURLINFO_HTTP_CODE);
        curl_close($chGen);

        if ($httpCode !== 200) {
            echo json_encode(array('status' => false, 'message' => 'Gemini API Error (HTTP ' . $httpCode . '): ' . $response));
            exit;
        }

        $resDecoded = json_decode($response, true);
        $outputText = 'Tidak ada ulasan yang dihasilkan.';
        if (isset($resDecoded['candidates'][0]['content']['parts'][0]['text'])) {
            $outputText = $resDecoded['candidates'][0]['content']['parts'][0]['text'];
        }

        // Regex Filter: Hapus segala bentuk preamble/header yang lolos secara otomatis
        $outputText = preg_replace('/^(.*?)### 1\./s', '### 1.', $outputText);
        $outputText = trim($outputText);

        $docxBase64 = null;
        $fileType = 'docx';
        $downloadFileName = 'Reviewed_Manuscript_' . $submissionId . '.docx';
        $targetFilePath = null;
        $generatedTempPath = null;
        $filesDir = Config::getVar('files', 'files_dir');

        // Mengambil submission file asli
        $submissionFiles = array();
        if (class_exists('Services') && Services::get('submissionFile')) {
            $submissionFiles = iterator_to_array(Services::get('submissionFile')->getMany(array('submissionIds' => array($submissionId))));
        }

        $originalGenreId = 1;
        foreach ($submissionFiles as $file) {
            $pathsToCheck = array();
            $relPath = method_exists($file, 'getData') ? $file->getData('path') : null;
            if ($relPath) {
                $pathsToCheck[] = $relPath;
                $pathsToCheck[] = rtrim($filesDir, '/') . '/' . ltrim($relPath, '/');
                $pathsToCheck[] = rtrim($filesDir, '/') . '/journals/' . $contextId . '/' . ltrim($relPath, '/');
            }
            if (method_exists($file, 'getFilePath')) {
                $pathsToCheck[] = $file->getFilePath();
            }

            foreach ($pathsToCheck as $p) {
                if ($p && file_exists($p) && is_file($p)) {
                    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
                    if ($ext === 'docx') {
                        $targetFilePath = $p;
                        if (method_exists($file, 'getGenreId') && $file->getGenreId()) {
                            $originalGenreId = $file->getGenreId();
                        }
                        break 2;
                    }
                }
            }
        }

        // 1. Modifikasi DOCX asli
        if ($targetFilePath && class_exists('ZipArchive')) {
            $generatedTempPath = tempnam(sys_get_temp_dir(), 'rev_') . '.docx';
            copy($targetFilePath, $generatedTempPath);

            $zip = new ZipArchive();
            if ($zip->open($generatedTempPath) === true) {
                $docXml = $zip->getFromName('word/document.xml');
                if ($docXml !== false) {
                    $escapedReview = htmlspecialchars($outputText, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    $paragraphs = explode("\n", $escapedReview);
                    
                    $appendXml = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
                    foreach ($paragraphs as $para) {
                        $pText = trim($para);
                        if (empty($pText)) continue;
                        $appendXml .= '<w:p><w:r><w:rPr><w:sz w:val="22"/></w:rPr><w:t>' . $pText . '</w:t></w:r></w:p>';
                    }

                    $docXml = str_replace('</w:body>', $appendXml . '</w:body>', $docXml);
                    $zip->addFromString('word/document.xml', $docXml);
                }
                $zip->close();
                $docxBase64 = base64_encode(file_get_contents($generatedTempPath));
            }
        }

        // 2. Standalone DOCX jika file asli tidak terbaca
        if (empty($docxBase64) && class_exists('ZipArchive')) {
            $generatedTempPath = $this->buildFreshDocx($title, $outputText);
            if ($generatedTempPath && file_exists($generatedTempPath)) {
                $docxBase64 = base64_encode(file_get_contents($generatedTempPath));
            }
        }

        // 3. Fallback jika ZipArchive tidak aktif
        if (empty($docxBase64)) {
            $fileType = 'txt';
            $downloadFileName = 'Reviewed_Manuscript_' . $submissionId . '.txt';
            $docxBase64 = base64_encode($outputText);
        }

        // 4. Auto Attach Reviewer File di OJS 3.3
        $autoAttached = false;
        if ($generatedTempPath && file_exists($generatedTempPath) && $reviewAssignmentId) {
            try {
                import('lib.pkp.classes.submission.SubmissionFile');
                $submissionFileService = Services::get('submissionFile');
                $user = $request->getUser();

                $newSubmissionFile = $submissionFileService->newDataObject(array(
                    'submissionId'   => $submissionId,
                    'fileStage'      => SUBMISSION_FILE_REVIEW_ATTACHMENT,
                    'assocType'      => ASSOC_TYPE_REVIEW_ASSIGNMENT,
                    'assocId'        => $reviewAssignmentId,
                    'genreId'        => $originalGenreId ? $originalGenreId : 1,
                    'uploaderUserId' => $user ? $user->getId() : 1,
                    'name'           => array($context->getPrimaryLocale() => $downloadFileName),
                ));

                if ($newSubmissionFile) {
                    $submissionFileService->add($newSubmissionFile, $generatedTempPath, $request);
                    $autoAttached = true;
                }
            } catch (\Throwable $e) {
                // Ignore fallback exception to protect JSON output
            }
            @unlink($generatedTempPath);
        }

        echo json_encode(array(
            'status' => true,
            'submissionId' => $submissionId,
            'review' => $outputText,
            'docxBase64' => $docxBase64,
            'fileType' => $fileType,
            'filename' => $downloadFileName,
            'autoAttached' => $autoAttached
        ));
        exit;
    }
}