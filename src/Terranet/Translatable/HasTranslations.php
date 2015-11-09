<?php

namespace Terranet\Translatable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Terranet\Translatable\Exception\LocalesNotDefinedException;

trait HasTranslations
{
    /**
     * Get translation for specified|default|fallback locale
     *
     * @param int $locale
     * @return Model|null
     */
    public function translate($locale = null)
    {
        if (! $locale) {
            $locale = \localizer\locale()->id();
        } else {
            $locale = (int) $locale;
        }

        return $this->getTranslation($locale);
    }

    /**
     * @param int       $locale
     * @return Model|null
     */
    protected function getTranslation($locale = null)
    {
        $locale = $locale ?: \localizer\locale()->id();

        return $translation = $this->getTranslationByLocaleKey($locale);
    }

    /**
     * Find a translation by loale
     *
     * @param $key
     * @return null
     */
    protected function getTranslationByLocaleKey($key)
    {
        foreach ($this->translations as $translation) {
            if ($translation->getAttribute($this->getLocaleKey()) == $key) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Locale key used for translations
     *
     * @return string
     */
    protected function getLocaleKey()
    {
        return ($this->localeKey ?: 'language_id');
    }

    /**
     * @alias getTranslationOrNew
     * @param $locale
     * @return Model|null
     */
    public function translateOrNew($locale)
    {
        return $this->getTranslationOrNew($locale);
    }

    /**
     * Get translation or create new if not exists
     *
     * @param $locale
     * @return Model|null
     */
    protected function getTranslationOrNew($locale)
    {
        if (($translation = $this->getTranslation($locale)) === null) {
            $translation = $this->createNewTranslation($locale);
        }

        return $translation;
    }

    protected function createNewTranslation($locale)
    {
        $translation = $this->getTranslationModel();

        $translation->setAttribute($this->getLocaleKey(), $locale);

        $this->translations->add($translation);

        return $translation;
    }

    /**
     * @return mixed
     */
    public function getTranslationModel()
    {
        $modelName = $this->getTranslationModelName();

        return new $modelName;
    }

    protected function getTranslationModelName()
    {
        return $this->translationModel ?: $this->getTranslationSuffix();
    }

    protected function getTranslationSuffix()
    {
        return get_class($this) . config('translatable::translation_suffix', 'Translation');
    }

    /**
     * Check if translation exists
     *
     * @param null $locale
     * @return bool
     */
    public function hasTranslation($locale = null)
    {
        $locale = $locale ?: \localizer\locale()->id();

        foreach ($this->translations as $translation) {
            if ($translation->getAttribute($this->getLocaleKey()) == $locale) {
                return true;
            }
        }

        return false;
    }

    /**
     * Translations relationship
     *
     * @return mixed
     */
    public function translations()
    {
        return $this->hasMany($this->getTranslationModelName(), $this->getRelationKey());
    }

    public function getRelationKey()
    {
        return property_exists($this, 'translationForeignKey') ? $this->translationForeignKey : $this->getForeignKey();
    }

    /**
     * Translatable attribute accessor
     *
     * @param $key
     * @return mixed|null
     */
    public function getAttribute($key)
    {
        if ($this->isKeyReturningTranslationText($key)) {
            if ($translation = $this->getTranslation()) {
                return $translation->$key;
            }

            return null;
        }

        return parent::getAttribute($key);
    }

    /**
     * Check if key is part of translatable attributes
     * @param $key
     * @return bool
     */
    protected function isKeyReturningTranslationText($key)
    {
        return $this->hasTranslatedAttributes() && in_array($key, $this->getTranslatedAttributes());
    }

    /**
     * Check if translated attributes are defined
     *
     * @return bool
     */
    public function hasTranslatedAttributes()
    {
        return property_exists($this, 'translatedAttributes');
    }

    /**
     * Get translated attributes
     *
     * @return array
     */
    public function getTranslatedAttributes()
    {
        return (array) $this->translatedAttributes;
    }

    /**
     * Translatable attribute mutator
     *
     * @param $key
     * @param $value
     */
    public function setAttribute($key, $value)
    {
        if ($this->hasTranslatedAttributes() && in_array($key, $this->translatedAttributes)) {
            $this->getTranslationOrNew(\localizer\locale()->id())->$key = $value;
        } else {
            parent::setAttribute($key, $value);
        }
    }

    /**
     * Override parent save method to handle translatable attributes
     *
     * @param array $options
     * @return bool
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            if (count($this->getDirty()) > 0) {
                // If $this->exists and dirty, parent::save() has to return true. If not,
                // an error has occurred. Therefore we shouldn't save the translations.
                if (parent::save($options)) {
                    return $this->saveTranslations();
                }

                return false;
            } else {
                // If $this->exists and not dirty, parent::save() skips saving and returns
                // false. So we have to save the translations
                if ($saved = $this->saveTranslations()) {
                    $this->fireModelEvent('saved', false);
                }

                return $saved;
            }
        } elseif (parent::save($options)) {
            // We save the translations only if the instance is saved in the database.
            return $this->saveTranslations();
        }

        return false;
    }

    /**
     * Save translatable attributes
     *
     * @return bool
     */
    protected function saveTranslations()
    {
        $saved = true;
        foreach ($this->translations as $translation) {
            if ($saved && $this->isTranslationDirty($translation)) {
                $translation->setAttribute($this->getRelationKey(), $this->getKey());
                $saved = $translation->save();
            }
        }

        return $saved;
    }

    protected function isTranslationDirty(Model $translation)
    {
        $dirtyAttributes = $translation->getDirty();

        unset($dirtyAttributes[$this->getLocaleKey()]);

        return count($dirtyAttributes) > 0;
    }

    /**
     * Override parent fill() method to handle translatable attributes
     *
     * @param array $attributes
     * @return bool
     */
    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($attributes as $key => $values) {
            if ($this->isKeyALocale($key)) {
                foreach ($values as $translationAttribute => $translationValue) {
                    if ($this->alwaysFillable() or $this->isFillable($translationAttribute)) {
                        $this->getTranslationOrNew($key)->$translationAttribute = $translationValue;
                    } elseif ($totallyGuarded) {
                        throw new MassAssignmentException($key);
                    }
                }
                unset($attributes[$key]);
            }
        }

        return parent::fill($attributes);
    }

    /**
     * Check if key is a valid locale
     *
     * @param $key
     * @return bool
     * @throws LocalesNotDefinedException
     */
    protected function isKeyALocale($key)
    {
        $locales = $this->getLocales();

        return in_array($key, $locales);
    }

    /**
     * Get list of all available locales
     *
     * @return array
     * @throws LocalesNotDefinedException
     */
    protected function getLocales()
    {
        return array_reduce(\localizer\locales(), function ($ids, $locale) {
            $ids[] = $locale->id();
            return $ids;
        }, []);
    }

    protected function alwaysFillable()
    {
        return config('translatable::always_fillable', false);
    }

    public function __isset($key)
    {
        return (($this->hasTranslatedAttributes() && in_array($key, $this->getTranslatedAttributes()))
                || parent::__isset($key));
    }

    /**
     * Allows fetching "Translatable" items with translations
     *
     * @note      : To limit the number of columns during query it's recommended to add select($columns) statement
     *            before calling translated() scope Examples: there are a few ways to get id=>title associative array
     *            for all posts:
     * @example1  : Post::active()->select(['post.id', 'tt.title'])->translated()->lists('title', 'id');
     * @example2  : Post::active()->translated()->lists('title', 'id'); will return the same list, but query will
     *            contain all translated columns, which can be a performance issue.
     * @example3  : Post::active()->select(['post.id', 'title', 'description'])->translated()->get();
     *            - will return all posts and query will contain only specified columns
     * @example4  : Post::active()->translated()->get();
     *            - the same result, but query contains all "Translatable" columns
     * @param Builder $query
     * @return Builder
     */
    public function scopeTranslated(Builder $query)
    {
        $mainTable  = $this->getTable();
        $keyName    = $this->getKeyName();
        $relKeyName = $this->getRelationKey();
        $localeKey  = $this->getLocaleKey();
        $joinTable  = $this->getTranslationModel()->getTable();
        $langId     = \localizer\locale()->id();

        $alias      = "tt";

        if ($this->isQueryWithoutColumns($query)) {
            $this->fillQueryWithTranslatedColumns($query, $mainTable, $keyName, $alias);
        }

        $query->leftJoin(
            "{$joinTable} AS {$alias}",
            function ($join) use ($mainTable, $keyName, $relKeyName, $localeKey, $alias, $langId) {
                $join->on("{$mainTable}.{$keyName}", '=', "{$alias}.{$relKeyName}")
                     ->where($localeKey, '=', (int) $langId);
            }
        );

        return $query;
    }

    /**
     * @param $query
     * @return bool
     */
    protected function isQueryWithoutColumns($query)
    {
        $columns = $query->getQuery()->columns;

        return ! $columns || $columns == ['*'];
    }

    /**
     * @param Builder $query
     * @param         $mainTable
     * @param         $keyName
     * @param         $alias
     */
    protected function fillQueryWithTranslatedColumns(Builder $query, $mainTable, $keyName, $alias)
    {
        $query->addSelect("{$mainTable}.{$keyName} AS {$keyName}");

        if ($this->hasTranslatedAttributes() && $columns = $this->getTranslatedAttributes()) {
            foreach ($columns as $column) {
                $query->addSelect("{$alias}.{$column}");
            }
        }
    }

    /**
     * Cast model to array
     *
     * @param bool $withTranslations - include translated columns or not
     * @return array
     */
    public function toArray($withTranslations = true)
    {
        $attributes = parent::toArray();

        if ($withTranslations && $this->hasTranslatedAttributes()) {
            foreach ($this->getTranslatedAttributes() as $field) {
                if ($translations = $this->getTranslation()) {
                    $attributes[$field] = $translations->$field;
                }
            }
        }

        return $attributes;
    }
}
