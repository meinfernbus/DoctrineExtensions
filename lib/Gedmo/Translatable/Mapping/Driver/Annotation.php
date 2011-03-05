<?php

namespace Gedmo\Translatable\Mapping\Driver;

use Gedmo\Mapping\Driver,
    Doctrine\Common\Persistence\Mapping\ClassMetadata,
    Doctrine\Common\Annotations\AnnotationReader,
    Gedmo\Exception\InvalidMappingException;

/**
 * This is an annotation mapping driver for Translatable
 * behavioral extension. Used for extraction of extended
 * metadata from Annotations specificaly for Translatable
 * extension.
 * 
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @package Gedmo.Translatable.Mapping.Driver
 * @subpackage Annotation
 * @link http://www.gediminasm.org
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class Annotation implements Driver
{
    /**
     * Annotation to identity translation entity to be used for translation storage
     */
    const ANNOTATION_ENTITY_CLASS = 'Gedmo\Translatable\Mapping\TranslationEntity';
    
    /**
     * Annotation to identify field as translatable 
     */
    const ANNOTATION_TRANSLATABLE = 'Gedmo\Translatable\Mapping\Translatable';
    
    /**
     * Annotation to identify field which can store used locale or language
     * alias is ANNOTATION_LANGUAGE
     */
    const ANNOTATION_LOCALE = 'Gedmo\Translatable\Mapping\Locale';
    
    /**
     * Annotation to identify field which can store used locale or language
     * alias is ANNOTATION_LOCALE
     */
    const ANNOTATION_LANGUAGE = 'Gedmo\Translatable\Mapping\Language';
    
    /**
     * List of types which are valid for translation,
     * this property is public and you can add some
     * other types in case it needs translation
     * 
     * @var array
     */
    protected $validTypes = array(
        'string',
        'text'
    );
    
    /**
     * {@inheritDoc}
     */
    public function validateFullMetadata(ClassMetadata $meta, array $config) {}
    
    /**
     * {@inheritDoc}
     */
    public function readExtendedMetadata(ClassMetadata $meta, array &$config) {
        require_once __DIR__ . '/../Annotations.php';
        $reader = new AnnotationReader();
        $reader->setAnnotationNamespaceAlias('Gedmo\Translatable\Mapping\\', 'gedmo');
        
        $class = $meta->getReflectionClass();
        // class annotations
        $classAnnotations = $reader->getClassAnnotations($class);
        if (isset($classAnnotations[self::ANNOTATION_ENTITY_CLASS])) {
            $annot = $classAnnotations[self::ANNOTATION_ENTITY_CLASS];
            if (!class_exists($annot->class)) {
                throw new InvalidMappingException("Translation class: {$annot->class} does not exist.");
            }
            $config['translationClass'] = $annot->class;
        }
        
        // property annotations
        foreach ($class->getProperties() as $property) {
            if ($meta->isMappedSuperclass && !$property->isPrivate() ||
                $meta->isInheritedField($property->name) ||
                isset($meta->associationMappings[$property->name]['inherited'])
            ) {
                continue;
            }
            // translatable property
            if ($translatable = $reader->getPropertyAnnotation($property, self::ANNOTATION_TRANSLATABLE)) {
                $field = $property->getName();
                if (!$meta->hasField($field)) {
                    throw new InvalidMappingException("Unable to find translatable [{$field}] as mapped property in entity - {$meta->name}");
                }
                if (!$this->isValidField($meta, $field)) {
                    throw new InvalidMappingException("Translatable field - [{$field}] type is not valid and must be 'string' or 'text' in class - {$meta->name}");
                }
                // fields cannot be overrided and throws mapping exception
                $config['fields'][] = $field;
            }
            // locale property
            if ($locale = $reader->getPropertyAnnotation($property, self::ANNOTATION_LOCALE)) {
                $field = $property->getName();
                if ($meta->hasField($field)) {
                    throw new InvalidMappingException("Locale field [{$field}] should not be mapped as column property in entity - {$meta->name}, since it makes no sence");
                }
                $config['locale'] = $field;
            } elseif ($language = $reader->getPropertyAnnotation($property, self::ANNOTATION_LANGUAGE)) {
                $field = $property->getName();
                if ($meta->hasField($field)) {
                    throw new InvalidMappingException("Language field [{$field}] should not be mapped as column property in entity - {$meta->name}, since it makes no sence");
                }
                $config['locale'] = $field;
            }
        }
    }
    
    /**
     * Checks if $field type is valid as Translatable field
     * 
     * @param ClassMetadata $meta
     * @param string $field
     * @return boolean
     */
    protected function isValidField(ClassMetadata $meta, $field)
    {
        $mapping = $meta->getFieldMapping($field);
        return $mapping && in_array($mapping['type'], $this->validTypes);
    }
}