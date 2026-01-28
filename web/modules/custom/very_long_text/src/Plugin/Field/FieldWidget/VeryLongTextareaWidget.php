<?php

namespace Drupal\very_long_text\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\Entity\FilterFormat;

/**
 * Plugin implementation of the 'very_long_text_textarea' widget.
 *
 * @FieldWidget(
 *   id = "very_long_text_textarea",
 *   label = @Translation("Very long text area"),
 *   field_types = {
 *     "very_long_text"
 *   }
 * )
 */
class VeryLongTextareaWidget extends StringTextareaWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'rows' => 20,
      'placeholder' => '',
      'enable_preview' => TRUE,
      'preview_format' => 'plain_text',
      'preview_height' => '200px',
      // Assistant-in-widget settings.
      'enable_assistant' => TRUE,
      'assistant_id' => 'canvasgov_markdown_designer',
      'assistant_height' => '420px',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    // Preview enable toggle.
    $element['enable_preview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable live preview'),
      '#default_value' => (bool) $this->getSetting('enable_preview'),
      '#description' => $this->t('Shows a filtered preview of the text below the textarea and updates it live while typing.'),
      '#weight' => 100,
    ];

    // Build list of available text formats for rendering preview.
    $formats = FilterFormat::loadMultiple();
    $options = [];
    foreach ($formats as $id => $format) {
      $options[$id] = $format->label();
    }
    if (empty($options)) {
      $options = ['plain_text' => $this->t('Plain text')];
    }

    $element['preview_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Preview text format'),
      '#options' => $options,
      '#default_value' => $this->getSetting('preview_format'),
      '#description' => $this->t('Select the text format used to render the live preview. This does not change how the value is stored.'),
      '#states' => [
        'visible' => [
          ':input[name$="[settings_edit_form][settings][enable_preview]"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 101,
    ];

    $element['preview_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Preview height'),
      '#default_value' => $this->getSetting('preview_height'),
      '#description' => $this->t('CSS height (e.g., 200px, 20rem).'),
      '#states' => [
        'visible' => [
          ':input[name$="[settings_edit_form][settings][enable_preview]"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 102,
    ];

    // Assistant settings (project-specific integration).
    $element['enable_assistant'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable in-widget AI assistant (project-specific)'),
      '#default_value' => (bool) $this->getSetting('enable_assistant'),
      '#description' => $this->t('Adds an assistant panel next to the textarea which can answer questions and propose edits. Requires the AI Chatbot module and a valid assistant ID.'),
      '#weight' => 200,
    ];
    $element['assistant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Assistant ID'),
      '#default_value' => (string) $this->getSetting('assistant_id'),
      '#description' => $this->t('Machine name of the AI assistant configuration (e.g., canvasgov_markdown_designer).'),
      '#states' => [
        'visible' => [
          ':input[name$="[settings_edit_form][settings][enable_assistant]"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 201,
    ];
    $element['assistant_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Assistant height'),
      '#default_value' => (string) $this->getSetting('assistant_height'),
      '#description' => $this->t('CSS height for the assistant panel (e.g., 420px).'),
      '#states' => [
        'visible' => [
          ':input[name$="[settings_edit_form][settings][enable_assistant]"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 202,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Live preview: @enabled', ['@enabled' => $this->getSetting('enable_preview') ? $this->t('enabled') : $this->t('disabled')]);
    if ($this->getSetting('enable_preview')) {
      $summary[] = $this->t('Preview format: @format', ['@format' => $this->getSetting('preview_format')]);
      $summary[] = $this->t('Preview height: @h', ['@h' => $this->getSetting('preview_height')]);
    }
    $summary[] = $this->t('Assistant: @enabled', ['@enabled' => $this->getSetting('enable_assistant') ? $this->t('enabled') : $this->t('disabled')]);
    if ($this->getSetting('enable_assistant')) {
      $summary[] = $this->t('Assistant ID: @id', ['@id' => $this->getSetting('assistant_id')]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Start with the base textarea widget for 'value'.
    $base = parent::formElement($items, $delta, $element, $form, $form_state);

    // Generate IDs for wrappers.
    $preview_wrapper_id = $this->getAjaxWrapperId($items, $delta, $form_state);
    $widget_wrapper_id = $this->getWidgetWrapperId($items, $delta, $form_state);

    // Build left column: textarea (+ preview if enabled).
    $format = (string) $this->getSetting('preview_format');
    $text = (string) ($items[$delta]->value ?? '');

    // Attach AJAX to textarea to update preview on keyup when preview is enabled.
    if ($this->getSetting('enable_preview')) {
      $base['value']['#ajax'] = [
        'callback' => [static::class, 'ajaxPreview'],
        'event' => 'keyup',
        'wrapper' => $preview_wrapper_id,
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ];
      $base['value']['#very_long_text_preview_format'] = $format;
    }

    $left = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['vlt-left'],
        'style' => 'min-width:0;',
      ],
    ] + $base;

    if ($this->getSetting('enable_preview')) {
      $left['preview'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => $preview_wrapper_id,
          'class' => ['very-long-text-preview'],
          'style' => 'margin-top: .5rem; padding: .5rem; background: #f8f9fa; border: 1px solid #e2e6ea; height: ' . $this->getSetting('preview_height') . '; overflow: auto;',
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('Live preview'),
          '#attributes' => ['class' => ['preview-label'], 'style' => 'font-weight: bold; margin-bottom: .25rem;'],
        ],
        'content' => static::buildProcessedText($text, $format),
      ];
    }

    // Build right column: assistant, if enabled and available.
    $right = [];
    if ($this->getSetting('enable_assistant')) {
      $assistant = $this->buildAssistantPanel((string) $this->getSetting('assistant_id'), (string) $this->getSetting('assistant_height'));
      if ($assistant) {
        $right = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['vlt-right'],
            'style' => 'display: flex; flex-direction: column;',
          ],
        ] + $assistant;
      }
    }

    // Combine into a two-column layout.
    $element = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $widget_wrapper_id,
        'class' => ['vlt-widget-wrapper'],
        'style' => 'display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items: stretch;',
      ],
      'left' => $left,
    ];
    if (!empty($right)) {
      $element['right'] = $right;
      // Attach our wiring JS to sync the assistant with the textarea, scoped to this wrapper.
      $element['#attached']['library'][] = 'very_long_text/assistant_wiring';
      $element['#attached']['drupalSettings']['very_long_text']['instances'][] = [
        'wrapperId' => $widget_wrapper_id,
      ];
    }

    return $element;
  }

  /**
   * AJAX callback returning the preview container.
   */
  public static function ajaxPreview(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'] ?? [];
    // Replace the last element key (likely 'value') with 'preview'.
    if (!empty($parents)) {
      array_pop($parents);
      $preview_parents = array_merge($parents, ['preview']);
      $preview = NestedArray::getValue($form, $preview_parents);
      // Update preview content with current text + configured format.
      // Current text from the triggering element value for most up-to-date input.
      $text = isset($trigger['#value']) ? (string) $trigger['#value'] : '';
      // Extract widget instance settings placed on the value element.
      $value_element = NestedArray::getValue($form, array_merge($parents, ['value']));
      $format = is_array($value_element) && isset($value_element['#very_long_text_preview_format'])
        ? $value_element['#very_long_text_preview_format']
        : 'plain_text';

      // Rebuild only the 'content' part of the preview.
      if (is_array($preview)) {
        $preview['content'] = static::buildProcessedText($text, $format);
        return $preview;
      }
    }
    return [];
  }

  /**
   * Helper: Generate a stable wrapper ID per widget element.
   */
  protected function getAjaxWrapperId(FieldItemListInterface $items, $delta, FormStateInterface $form_state) {
    $parents = array_merge($this->fieldDefinition->getName() ? [$this->fieldDefinition->getName()] : [], [$delta]);
    $id = implode('-', array_map('strval', $parents));
    return 'very-long-text-preview-' . $id . '-' . substr(hash('crc32b', $this->fieldDefinition->getUniqueIdentifier() . $delta), 0, 8);
  }

  /**
   * Helper: Unique widget wrapper ID.
   */
  protected function getWidgetWrapperId(FieldItemListInterface $items, $delta, FormStateInterface $form_state): string {
    $parents = array_merge($this->fieldDefinition->getName() ? [$this->fieldDefinition->getName()] : [], [$delta]);
    $id = implode('-', array_map('strval', $parents));
    return 'very-long-text-widget-' . $id . '-' . substr(hash('crc32b', $this->fieldDefinition->getUniqueIdentifier() . $delta), 0, 8);
  }

  /**
   * Build processed_text render array for preview.
   */
  protected static function buildProcessedText(string $text, string $format) {
    // Fall back to plain_text if the selected format does not exist.
    $formats = FilterFormat::loadMultiple();
    if (!isset($formats[$format])) {
      $format = 'plain_text';
    }
    return [
      '#type' => 'processed_text',
      '#text' => $text,
      '#format' => $format,
    ];
  }

  /**
   * Build the assistant panel via ai_chatbot block if available.
   *
   * @return array|null
   *   A render array for the assistant, or NULL if unavailable.
   */
  protected function buildAssistantPanel(string $assistant_id, string $height): ?array {
    // Ensure the block plugin exists before attempting to render.
    if (!\Drupal::hasService('plugin.manager.block')) {
      return NULL;
    }
    $block_manager = \Drupal::service('plugin.manager.block');
    // Verify the block plugin is available (ai_chatbot module enabled).
    $definitions = $block_manager->getDefinitions();
    if (!isset($definitions['ai_deepchat_block'])) {
      return NULL;
    }

    $config = [
      'label' => $this->t('Designer assistant'),
      'label_display' => FALSE,
      'ai_assistant' => $assistant_id,
      'width' => '100%',
      'height' => $height,
      // Empty placement to avoid sticky/floating positioning; we'll render inline.
      'placement' => '',
      'collapse_minimal' => FALSE,
      'toggle_state' => 'open',
      'show_copy_icon' => TRUE,
    ];

    /** @var \Drupal\Core\Block\BlockBase $block */
    $block = $block_manager->createInstance('ai_deepchat_block', $config);
    $build = $block->build();
    // Ensure DeepChat JS can find the element. Contrib JS looks for a
    // '.deepchat-element' inside the container; expose it as a class
    // attribute on the <deep-chat> tag via deepchat_settings.
    if (!isset($build['#deepchat_settings']) || !is_array($build['#deepchat_settings'])) {
      $build['#deepchat_settings'] = [];
    }
    $build['#deepchat_settings']['class'] = 'deepchat-element';
    // Provide a container wrapper/class for inline styling and height control.
    $height_css = htmlspecialchars($height, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $build['#prefix'] = '<div class="vlt-assistant" style="--vlt-assistant-height: ' . $height_css . ';">';
    $build['#suffix'] = '</div>';
    return $build;
  }

}
