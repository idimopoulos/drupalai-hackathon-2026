<?php

namespace Drupal\very_long_text\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;

/**
 * Plugin implementation of the 'very_long_text_plain' formatter.
 *
 * @FieldFormatter(
 *   id = "very_long_text_plain",
 *   label = @Translation("Plain text"),
 *   field_types = {
 *     "very_long_text"
 *   }
 * )
 */
class VeryLongTextPlainFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'nl2br' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['nl2br'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Convert newlines to <br>'),
      '#default_value' => $this->getSetting('nl2br'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->getSetting('nl2br') ? $this->t('Newlines converted to <br>') : $this->t('Raw newlines');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $convert = (bool) $this->getSetting('nl2br');

    foreach ($items as $delta => $item) {
      $text = (string) $item->value;
      $safe = Html::escape($text);
      $markup = $convert ? nl2br($safe) : $safe;
      $elements[$delta] = [
        '#markup' => $markup,
      ];
    }

    return $elements;
  }

}
