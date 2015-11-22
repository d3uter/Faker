<?php

namespace Faker\ODM\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ODM\EntityManagerInterface;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Document;

/**
 * Service class for populating a table through a Doctrine Entity class.
 */
class DocumentPopulator
{
    /**
     * @var ClassMetadata
     */
    protected $class;
    /**
     * @var array
     */
    protected $fieldFormatters = array();
    /**
     * @var array
     */
    protected $modifiers = array();

    /**
     * Class constructor.
     *
     * @param ClassMetadata $class
     */
    public function __construct(ClassMetadata $class)
    {
        $this->class = $class;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class->getName();
    }

    /**
     * @param $columnFormatters
     */
    public function setFieldFormatters($fieldFormatters)
    {
        $this->fieldFormatters = $fieldFormatters;
    }

    /**
     * @return array
     */
    public function getFieldFormatters()
    {
        return $this->fieldFormatters;
    }

    public function mergeFieldFormattersWith($fieldFormatters)
    {
        $this->fieldFormatters = array_merge($this->fieldFormatters, $fieldFormatters);
    }

    /**
     * @param array $modifiers
     */
    public function setModifiers(array $modifiers)
    {
        $this->modifiers = $modifiers;
    }

    /**
     * @return array
     */
    public function getModifiers()
    {
        return $this->modifiers;
    }

    /**
     * @param array $modifiers
     */
    public function mergeModifiersWith(array $modifiers)
    {
        $this->modifiers = array_merge($this->modifiers, $modifiers);
    }

    /**
     * @param \Faker\Generator $generator
     * @return array
     */
    public function guessFieldFormatters(\Faker\Generator $generator)
    {
        $formatters = array();
        $nameGuesser = new \Faker\Guesser\Name($generator);
        $columnTypeGuesser = new FieldTypeGuesser($generator);
        foreach ($this->class->getFieldNames() as $fieldName) {
            if ($this->class->isIdentifier($fieldName) || !$this->class->hasField($fieldName)) {
                continue;
            }

            $size = isset($this->class->fieldMappings[$fieldName]['length']) ? $this->class->fieldMappings[$fieldName]['length'] : null;
            if ($formatter = $nameGuesser->guessFormat($fieldName, $size)) {
                $formatters[$fieldName] = $formatter;
                continue;
            }
            if ($formatter = $columnTypeGuesser->guessFormat($fieldName, $this->class)) {
                $formatters[$fieldName] = $formatter;
                continue;
            }
        }

        foreach ($this->class->getAssociationNames() as $assocName) {
            if ($this->class->isCollectionValuedAssociation($assocName)) {
                continue;
            }

            $relatedClass = $this->class->getAssociationTargetClass($assocName);

            $unique = $optional = false;
            $mappings = $this->class->associationMappings;
//            foreach ($mappings as $mapping) {
//                if ($mapping['targetDocument'] == $relatedClass) {
//                    if ($mapping['type'] == ClassMetadata::ONE_TO_ONE) {
//                        $unique = true;
//                        $optional = isset($mapping['joinColumns'][0]['nullable']) ? $mapping['joinColumns'][0]['nullable'] : false;
//                        break;
//                    }
//
//
//
//
//
//
//                }
//            }

            $index = 0;
            $formatters[$assocName] = function ($inserted) use ($relatedClass, &$index, $unique, $optional) {

                if (isset($inserted[$relatedClass])) {
                    if ($unique) {
                        $related = null;
                        if (isset($inserted[$relatedClass][$index]) || !$optional) {
                            $related = $inserted[$relatedClass][$index];
                        }

                        $index++;

                        return $related;
                    }

                    return $inserted[$relatedClass][mt_rand(0, count($inserted[$relatedClass]) - 1)];
                }

                return null;
            };
        }

        return $formatters;
    }

    /**
     * Insert one new record using the Entity class.
     * @param ObjectManager $manager
     * @param bool $generateId
     * @return EntityPopulator
     */
    public function execute(ObjectManager $manager, $insertedEntities, $generateId = false)
    {
        /** @var Document $obj */
        $obj = $this->class->newInstance();

        $this->fillFields($obj, $insertedEntities);
        $this->callMethods($obj, $insertedEntities);



//        $mappings = $this->class->associationMappings;
//            foreach ($mappings as $mapping) {
//                if ($mapping['targetDocument'] == $obj) {
//                    if ($mapping['type'] == ClassMetadata::EMBED_ONE) {
//
//                        1==1;
//                    }
//                }
//            }

        if($this->class->isEmbeddedDocument){


            //$this->class->reflFields[$idName]->setValue($obj);
            //setFieldValue
            $this->class->reflFields[$field]->setValue($obj);
        }

        if ($generateId) {
            $idsName = $this->class->getIdentifier();
            foreach ($idsName as $idName) {
                $id = $this->generateId($obj, $idName, $manager);
                $this->class->reflFields[$idName]->setValue($obj, $id);
            }
        }





        $manager->persist($obj);

        return $obj;
    }

    private function fillFields($obj, $insertedEntities)
    {
        foreach ($this->fieldFormatters as $field => $format) {
            if (null !== $format) {
                $value = is_callable($format) ? $format($insertedEntities, $obj) : $format;
                $this->class->reflFields[$field]->setValue($obj, $value);
            }
        }
    }

    private function callMethods($obj, $insertedEntities)
    {
        foreach ($this->getModifiers() as $modifier) {
            $modifier($obj, $insertedEntities);
        }
    }

    /**
     * @param EntityManagerInterface $manager
     * @return int|null
     */
    private function generateId($obj, $column, EntityManagerInterface $manager)
    {
        /* @var $repository \Doctrine\ORM\EntityRepository */
        $repository = $manager->getRepository(get_class($obj));
        $result = $repository->createQueryBuilder('e')
                ->select(sprintf('e.%s', $column))
                ->getQuery()
                ->getResult();
        $ids = array_map('current', $result);

        $id = null;
        do {
            $id = mt_rand();
        } while (in_array($id, $ids));

        return $id;
    }
}
