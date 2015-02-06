Laravel-Translatable
====================

This is a Laravel package for translatable models. 
Its goal is to remove the complexity in retrieving and storing multilingual model instances. 

With this package you write less code, as the translations are being fetched/saved when you fetch/save your instance.

If you want to store translations of your models into the database, this package is for you.

## Demo

Getting translated attributes


    $page = Page::find(1);
    echo $page->translate('en')->title;


Saving translated attributes

    
    $country = Country::where('slug', '=', 'en')->first();
    echo $country->translate('en')->name;
  
    $country->translate('en')->name = 'abc';
    $country->save(); 
    

Filling multiple translations

    $data = array(
       'slug' => 'ro',
        1  => [
            'name' => 'Romania'
        ],
        2  => [
            'name' => 'Russian Federation'
        ]
    );

    $country = Country::create($data);
  
    echo $country->translate('fr')->name; // Grèce
    

## Installation

### Step 1

Add the package in your composer.json file and run `composer update`.

    ```json
    {
        "require": {
            "terranet/translatable": "1.0.*"
        }
    }
    ```

### Step 2

Let's say you have a model `Country`. To save the translations of countries you need one extra table `country_langs`.

Create your migrations:

    Schema::create('languages', function(Blueprint $table)
    {
        $table->increments('id');
        $table->string('slug', 2)->index()->unique();
        $table->string('title');
        
        $table->timestamps();
    });
    
    Schema::create('countries', function(Blueprint $table)
    {
        $table->increments('id');
        $table->string('iso');
        $table->timestamps();
    });    

    Schema::create('country_translations', function(Blueprint $table)
    {
        $table->increments('id');
        $table->integer('country_id')->unsigned();
        $table->string('lang_id')->index();
        $table->string('name');
        $table->unique(['country_id','lang_id']);
        $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
        $table->foreign('lang_id')->references('id')->on('languages')->onDelete('cascade');
    });

### Step 3

The models:

The translatable model `Country` should implement the interface `Terranet\Translatable\Translatable` and use `Terranet\Translatable\HasTranslations` trait. 

The convention for the translation model is `CountryLang`.

    class Country extends Eloquent implements Translatable {
        use HasTranslations;
    
        public $translatedAttributes = array('name');
        protected $fillable = ['slug', 'name'];

    }

    class CountryLang extends Eloquent {
        public $timestamps = false;
        protected $fillable = ['name'];
    }
    

The array `$translatedAttributes` contains the names of the fields being translated in the "Translation" model.