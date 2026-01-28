<?php

namespace Drupal\very_long_text\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'very_long_text' field type.
 *
 * @FieldType(
 *   id = "very_long_text",
 *   label = @Translation("Very long text"),
 *   description = @Translation("Stores very long text as a big BLOB."),
 *   category = "plain_text",
 *   default_widget = "very_long_text_textarea",
 *   default_formatter = "very_long_text_plain"
 * )
 */
class VeryLongTextItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Text'))
      ->setRequired(FALSE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'blob',
          'size' => 'big',
          'serialize' => FALSE,
          'not null' => FALSE,
        ],
      ],
      // No indexes by default.
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

}
