<?php

namespace Terranet\Translatable;

interface Translatable
{
    public function translate($locale = null);

    public function translateOrNew($locale);

    public function hasTranslation($locale = null);
}
