<?php

namespace Drupal\canvasgov;

use Drupal\ai_assistant_api\AiAssistantApiRunner as CoreAiAssistantApiRunner;
use Drupal\ai\OperationType\Chat\ChatOutput;

/**
 * A safer AiAssistantApiRunner that ignores malformed/empty actions.
 *
 * This subclass adds defensive checks around the actions loop to prevent
 * warnings like: Undefined array key "plugin" in AiAssistantApiRunner->process().
 */
class SafeAiAssistantApiRunner extends CoreAiAssistantApiRunner {

  /**
   * {@inheritdoc}
   */
  public function process() {
    // Validate that we can run.
    $this->validateAssistant();

    // Reset everything before running.
    $this->resetStructuredResults();
    $this->resetOutputContexts();
    $instance = NULL;

    // If we are using an agent as assistant.
    if ($this->assistant->get('ai_agent') && $this->moduleHandler->moduleExists('ai_agents')) {
      // @phpstan-ignore-next-line
      return \Drupal::service('ai_assistant_api.agent_runner')->runAsAgent(
        $this->assistant->get('ai_agent'),
        $this->getMessageHistory(),
        $this->getProviderAndModel(),
        $this->getThreadsKey(),
        $this->getVerboseMode(),
      );
    }

    try {
      $system_prompt = $this->assistant->get('system_prompt');
      // If the site isn't configured to use custom prompts, override with the
      // latest version of the prompt from the module.
      if (!$this->settings->get('ai_assistant_custom_prompts', FALSE)) {
        $path = $this->moduleHandler->getModule('ai_assistant_api')->getPath() . '/resources/';
        $system_prompt = file_get_contents($path . 'system_prompt.txt');
      }
      if ($system_prompt) {
        $return = $this->assistantMessage(TRUE);

        // If it's a normal response, we just return it.
        if ($return instanceof ChatOutput) {
          return $return;
        }

        $defaults = $this->getProviderAndModel();

        // Defensive: ensure actions is a list of arrays with required keys.
        $actions = [];
        if (is_array($return) && isset($return['actions']) && is_array($return['actions'])) {
          $actions = $return['actions'];
        }
        $actions_enabled = $this->assistant->get('actions_enabled');
        if (!is_array($actions_enabled)) {
          $actions_enabled = [];
        }

        foreach ($actions as $action) {
          if (!is_array($action) || empty($action['plugin']) || empty($action['action'])) {
            // Skip malformed actions.
            continue;
          }
          $this->usingAction = TRUE;
          $instance = $this->actions->createInstance($action['plugin'], $actions_enabled[$action['plugin']] ?? []);
          $instance->setAssistant($this->assistant);
          $instance->setThreadId($this->threadId);
          $instance->setAiProvider($this->aiProvider->createInstance($defaults['provider_id']));
          $instance->setMessages($this->getMessageHistory());
          // Pass the assistant and the thread id so it can be tagged.
          $action['ai_assistant_api'] = $this->assistant->id();
          $action['thread_id'] = $this->threadId;
          $instance->triggerAction($action['action'], $action);
        }
      }
    }
    catch (\Exception $e) {
      // Log the error.
      $this->loggerChannelFactory->get('ai_assistant_api')->error($e->getMessage());
      $error_message = str_replace('[error_message]', $e->getMessage(), $this->assistant->get('error_message'));
      if (!is_null($instance)) {
        $instance->triggerRollback();
      }
      if ($this->throwException) {
        throw $e;
      }
      // Return the error message.
      return new ChatOutput(
        new \Drupal\ai\OperationType\Chat\ChatMessage('assistant', $error_message),
        [$error_message],
        [],
      );
    }

    // Run the response to the final assistants message.
    return $this->assistantMessage();
  }

}
