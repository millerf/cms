<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\app\elements;

use Craft;
use craft\app\base\Element;
use craft\app\base\ElementInterface;
use craft\app\db\Query;
use craft\app\elements\actions\Delete;
use craft\app\elements\actions\Edit;
use craft\app\elements\actions\NewChild;
use craft\app\elements\actions\SetStatus;
use craft\app\elements\actions\View;
use craft\app\elements\db\CategoryQuery;
use craft\app\helpers\Url;
use craft\app\models\CategoryGroup;
use yii\base\InvalidConfigException;

/**
 * Category represents a category element.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class Category extends Element
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName()
    {
        return Craft::t('app', 'Category');
    }

    /**
     * @inheritdoc
     */
    public static function hasContent()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses()
    {
        return true;
    }

    /**
     * @inheritdoc
     *
     * @return CategoryQuery The newly created [[CategoryQuery]] instance.
     */
    public static function find()
    {
        return new CategoryQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public static function getSources($context = null)
    {
        $sources = [];

        if ($context == 'index') {
            $groups = Craft::$app->getCategories()->getEditableGroups();
        } else {
            $groups = Craft::$app->getCategories()->getAllGroups();
        }

        foreach ($groups as $group) {
            $key = 'group:'.$group->id;

            $sources[$key] = [
                'label' => Craft::t('site', $group->name),
                'data' => ['handle' => $group->handle],
                'criteria' => ['groupId' => $group->id],
                'structureId' => $group->structureId,
                'structureEditable' => Craft::$app->getUser()->checkPermission('editCategories:'.$group->id),
            ];
        }

        // Allow plugins to modify the sources
        Craft::$app->getPlugins()->call('modifyCategorySources',
            [&$sources, $context]);

        return $sources;
    }

    /**
     * @inheritdoc
     */
    public static function getAvailableActions($source = null)
    {
        // Get the group we need to check permissions on
        if (preg_match('/^group:(\d+)$/', $source, $matches)) {
            $group = Craft::$app->getCategories()->getGroupById($matches[1]);
        }

        // Now figure out what we can do with it
        $actions = [];

        if (!empty($group)) {
            // Set Status
            $actions[] = SetStatus::className();

            if ($group->hasUrls) {
                // View
                $actions[] = Craft::$app->getElements()->createAction([
                    'type' => View::className(),
                    'label' => Craft::t('app', 'View category'),
                ]);
            }

            // Edit
            $actions[] = Craft::$app->getElements()->createAction([
                'type' => Edit::className(),
                'label' => Craft::t('app', 'Edit category'),
            ]);

            // New Child
            $structure = Craft::$app->getStructures()->getStructureById($group->structureId);

            if ($structure) {
                $actions[] = Craft::$app->getElements()->createAction([
                    'type' => NewChild::className(),
                    'label' => Craft::t('app', 'Create a new child category'),
                    'maxLevels' => $structure->maxLevels,
                    'newChildUrl' => 'categories/'.$group->handle.'/new',
                ]);
            }

            // Delete
            $actions[] = Craft::$app->getElements()->createAction([
                'type' => Delete::className(),
                'confirmationMessage' => Craft::t('app', 'Are you sure you want to delete the selected categories?'),
                'successMessage' => Craft::t('app', 'Categories deleted.'),
            ]);
        }

        // Allow plugins to add additional actions
        $allPluginActions = Craft::$app->getPlugins()->call('addCategoryActions',
            [$source], true);

        foreach ($allPluginActions as $pluginActions) {
            $actions = array_merge($actions, $pluginActions);
        }

        return $actions;
    }

    /**
     * @inheritdoc
     */
    public static function defineSortableAttributes()
    {
        $attributes = [
            'title' => Craft::t('app', 'Title'),
            'uri' => Craft::t('app', 'URI'),
            'elements.dateCreated' => Craft::t('app', 'Date Created'),
            'elements.dateUpdated' => Craft::t('app', 'Date Updated'),
        ];

        // Allow plugins to modify the attributes
        Craft::$app->getPlugins()->call('modifyCategorySortableAttributes',
            [&$attributes]);

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public static function defineAvailableTableAttributes()
    {
        $attributes = [
            'title' => ['label' => Craft::t('app', 'Title')],
            'uri' => ['label' => Craft::t('app', 'URI')],
            'link' => ['label' => Craft::t('app', 'Link'), 'icon' => 'world'],
            'id' => ['label' => Craft::t('app', 'ID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
        ];

        // Allow plugins to modify the attributes
        $pluginAttributes = Craft::$app->getPlugins()->call('defineAdditionalCategoryTableAttributes', [], true);

        foreach ($pluginAttributes as $thisPluginAttributes) {
            $attributes = array_merge($attributes, $thisPluginAttributes);
        }

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultTableAttributes($source = null)
    {
        $attributes = ['link'];

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public static function getTableAttributeHtml(ElementInterface $element, $attribute)
    {
        /** @var Category $element */
        // First give plugins a chance to set this
        $pluginAttributeHtml = Craft::$app->getPlugins()->callFirst('getCategoryTableAttributeHtml',
            [$element, $attribute], true);

        if ($pluginAttributeHtml !== null) {
            return $pluginAttributeHtml;
        }

        return parent::getTableAttributeHtml($element, $attribute);
    }

    /**
     * @inheritdoc
     */
    public static function getEditorHtml(ElementInterface $element)
    {
        /** @var Category $element */
        $html = Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'textField',
            [
                [
                    'label' => Craft::t('app', 'Title'),
                    'locale' => $element->locale,
                    'id' => 'title',
                    'name' => 'title',
                    'value' => $element->title,
                    'errors' => $element->getErrors('title'),
                    'first' => true,
                    'autofocus' => true,
                    'required' => true
                ]
            ]);

        $html .= Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'textField',
            [
                [
                    'label' => Craft::t('app', 'Slug'),
                    'locale' => $element->locale,
                    'id' => 'slug',
                    'name' => 'slug',
                    'value' => $element->slug,
                    'errors' => $element->getErrors('slug'),
                    'required' => true
                ]
            ]);

        $html .= parent::getEditorHtml($element);

        return $html;
    }

    /**
     * @inheritdoc
     */
    public static function saveElement(ElementInterface $element, $params)
    {
        /** @var Category $element */
        if (isset($params['slug'])) {
            $element->slug = $params['slug'];
        }

        return Craft::$app->getCategories()->saveCategory($element);
    }

    /**
     * @inheritdoc
     */
    public static function getElementRoute(ElementInterface $element)
    {
        /** @var Category $element */
        $group = $element->getGroup();

        // Make sure the group is set to have URLs
        if ($group->hasUrls) {
            return [
                'templates/render',
                [
                    'template' => $group->template,
                    'variables' => [
                        'category' => $element
                    ]
                ]
            ];
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public static function onAfterMoveElementInStructure(ElementInterface $element, $structureId)
    {
        /** @var Category $element */
        // Was the category moved within its group's structure?
        if ($element->getGroup()->structureId == $structureId) {
            // Update its URI
            Craft::$app->getElements()->updateElementSlugAndUri($element, true, true, true);

            // Make sure that each of the category's ancestors are related wherever the category is related
            $newRelationValues = [];

            $ancestorIds = $element->getAncestors()->ids();

            $sources = (new Query())
                ->select(['fieldId', 'sourceId', 'sourceLocale'])
                ->from('{{%relations}}')
                ->where('targetId = :categoryId',
                    [':categoryId' => $element->id])
                ->all();

            foreach ($sources as $source) {
                $existingAncestorRelations = (new Query())
                    ->select('targetId')
                    ->from('{{%relations}}')
                    ->where([
                        'and',
                        'fieldId = :fieldId',
                        'sourceId = :sourceId',
                        'sourceLocale = :sourceLocale',
                        ['in', 'targetId', $ancestorIds]
                    ], [
                        ':fieldId' => $source['fieldId'],
                        ':sourceId' => $source['sourceId'],
                        ':sourceLocale' => $source['sourceLocale']
                    ])
                    ->column();

                $missingAncestorRelations = array_diff($ancestorIds, $existingAncestorRelations);

                foreach ($missingAncestorRelations as $categoryId) {
                    $newRelationValues[] = [
                        $source['fieldId'],
                        $source['sourceId'],
                        $source['sourceLocale'],
                        $categoryId
                    ];
                }
            }

            if ($newRelationValues) {
                Craft::$app->getDb()->createCommand()
                    ->batchInsert(
                        '{{%relations}}',
                        ['fieldId', 'sourceId', 'sourceLocale', 'targetId'],
                        $newRelationValues)
                    ->execute();
            }
        }
    }

    // Properties
    // =========================================================================

    /**
     * @var integer Group ID
     */
    public $groupId;

    /**
     * @var integer New parent ID
     */
    public $newParentId;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();

        $rules[] = [
            ['groupId'],
            'number',
            'min' => -2147483648,
            'max' => 2147483647,
            'integerOnly' => true
        ];
        $rules[] = [
            ['newParentId'],
            'number',
            'min' => -2147483648,
            'max' => 2147483647,
            'integerOnly' => true
        ];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return $this->getGroup()->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function getUrlFormat()
    {
        $group = $this->getGroup();

        if ($group && $group->hasUrls) {
            $groupLocales = $group->getLocales();

            if (isset($groupLocales[$this->locale])) {
                if ($this->level > 1) {
                    return $groupLocales[$this->locale]->nestedUrlFormat;
                }

                return $groupLocales[$this->locale]->urlFormat;
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getIsEditable()
    {
        return Craft::$app->getUser()->checkPermission('editCategories:'.$this->groupId);
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        $group = $this->getGroup();

        $url = Url::getCpUrl('categories/'.$group->handle.'/'.$this->id.($this->slug ? '-'.$this->slug : ''));

        if (Craft::$app->getIsLocalized() && $this->locale != Craft::$app->language) {
            $url .= '/'.$this->locale;
        }

        return $url;
    }

    /**
     * Returns the category's group.
     *
     * @return CategoryGroup
     * @throws InvalidConfigException if [[groupId]] is invalid
     */
    public function getGroup()
    {
        if ($this->groupId) {
            $group = Craft::$app->getCategories()->getGroupById($this->groupId);

            if ($group) {
                return $group;
            }
        }

        throw new InvalidConfigException('Invalid category group ID: '.$this->groupId);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function resolveStructureId()
    {
        return $this->getGroup()->structureId;
    }
}
