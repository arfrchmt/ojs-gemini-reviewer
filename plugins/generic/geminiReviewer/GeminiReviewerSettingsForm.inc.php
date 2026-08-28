<?php
import('lib.pkp.classes.form.Form');

class GeminiReviewerSettingsForm extends Form {
    public $plugin;
    public $contextId;

    public function __construct($plugin, $contextId) {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData() {
        $this->setData('apiKey', $this->plugin->getSetting($this->contextId, 'apiKey'));
        $this->setData('model', $this->plugin->getSetting($this->contextId, 'model') ?: 'gemini-1.5-flash');
        $this->setData('customPrompt', $this->plugin->getSetting($this->contextId, 'customPrompt'));
    }

    public function readInputData() {
        $this->readUserVars(array('apiKey', 'model', 'customPrompt'));
    }

    public function execute(...$functionArgs) {
        $this->plugin->updateSetting($this->contextId, 'apiKey', trim($this->getData('apiKey')));
        $this->plugin->updateSetting($this->contextId, 'model', $this->getData('model'));
        $this->plugin->updateSetting($this->contextId, 'customPrompt', $this->getData('customPrompt'));
        parent::execute(...$functionArgs);
    }
}
