<?php
import('lib.pkp.classes.plugins.GenericPlugin');

class GeminiReviewerPlugin extends GenericPlugin {

    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            HookRegistry::register('LoadHandler', array($this, 'callbackLoadHandler'));
            HookRegistry::register('TemplateManager::display', array($this, 'callbackTemplateDisplay'));
        }
        return $success;
    }

    public function getDisplayName() {
        return 'Gemini AI Manuscript Reviewer';
    }

    public function getDescription() {
        return 'Mengintegrasikan Google Gemini API untuk membantu dewan editor dan reviewer menelaah naskah secara otomatis.';
    }

    public function getActions($request, $actionArgs) {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        $actions[] = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );
        return $actions;
    }

    public function manage($args, $request) {
        $verb = $request->getUserVar('verb');
        if ($verb === 'settings') {
            $context = $request->getContext();
            $contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

            if ($request->getUserVar('save')) {
                $customPrompt = (string) $request->getUserVar('customPrompt');
                $showEditor   = $request->getUserVar('showEditor') ? 1 : 0;
                $showReviewer = $request->getUserVar('showReviewer') ? 1 : 0;

                $this->updateSetting($contextId, 'customPrompt', $customPrompt, 'string');
                $this->updateSetting($contextId, 'showEditor', $showEditor, 'bool');
                $this->updateSetting($contextId, 'showReviewer', $showReviewer, 'bool');

                import('lib.pkp.classes.core.JSONMessage');
                return new JSONMessage(true);
            }

            $templateMgr = TemplateManager::getManager($request);
            $defaultPrompt = "You are an expert double-blind academic peer reviewer for a reputable scientific journal. Evaluate the manuscript thoroughly based on: 1) Contribution & Novelty, 2) Methodological Soundness, 3) Logical Flow & Organization, 4) Critical Flaws & Weaknesses, 5) Concrete Suggestions for Improvement, and 6) Verdict Recommendation (Accept, Minor Revisions, Major Revisions, or Decline).";

            $customPrompt = $this->getSetting($contextId, 'customPrompt');
            if (empty($customPrompt)) {
                $customPrompt = $defaultPrompt;
            }

            $showEditor = (bool) $this->getSetting($contextId, 'showEditor');
            $showReviewer = (bool) $this->getSetting($contextId, 'showReviewer');

            $templateMgr->assign(array(
                'customPrompt' => $customPrompt,
                'showEditor'   => $showEditor,
                'showReviewer' => $showReviewer,
                'pluginName'   => $this->getName(),
            ));

            return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('settingsForm.tpl')));
        }
        return parent::manage($args, $request);
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

    public function callbackTemplateDisplay($hookName, $args) {
        $templateMgr = $args[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

        // Selalu oper status visibilitas aktif ke setiap template render
        $showEditor = (bool) $this->getSetting($contextId, 'showEditor');
        $showReviewer = (bool) $this->getSetting($contextId, 'showReviewer');

        $templateMgr->assign('geminiShowEditor', $showEditor);
        $templateMgr->assign('geminiShowReviewer', $showReviewer);

        return false;
    }
}