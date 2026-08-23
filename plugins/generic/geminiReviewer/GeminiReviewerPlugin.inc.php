<?php
import('lib.pkp.classes.plugins.GenericPlugin');

class GeminiReviewerPlugin extends GenericPlugin {
    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);
        if ($success) {
            HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));
        }
        return $success;
    }

    public function getDisplayName() {
        return 'Gemini AI Manuscript Reviewer';
    }

    public function getDescription() {
        return 'Mengintegrasikan Google Gemini API untuk membantu dewan editor menganalisis naskah secara otomatis.';
    }

    public function callbackLoadHandler($hookName, $args) {
        $page = $args[0];
        if ($page === 'geminiReviewerHandler') {
            $this->import('GeminiReviewerHandler');
            define('HANDLER_CLASS', 'GeminiReviewerHandler');
            return true;
        }
        return false;
    }
}
