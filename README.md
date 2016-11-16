#Yii2 migration class
##Description
Yii2 class Migration provide more flexibility usage interface then basic Yii2 class, so can be used instead of it.
It is backward compatible with basic migration class.



##Usage 

This is a sample of some migration with new interface.
```php
class m000000_000000_tbl_country extends Migration
{
    // common table's info
    protected $tableName = '{{%country}}';
    //
    //
    // structure table's info
    protected $columns = [
        'code'                      => 'CHAR(2) NOT NULL PRIMARY KEY comment "ISO 3166-1 alpha-2"',
        self::COLUMN_NAME           => 'VARCHAR(255) NOT NULL',
        'phone_country'             => 'VARCHAR(10) NOT NULL',
        'language'                  => 'CHAR(2) DEFAULT NULL comment "ISO 639-1"',
        'currency'                  => 'CHAR(3) DEFAULT NULL comment "ISO 4217"',
        self::COLUMN_ISACTIVE       => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT '.self::BOOL_ON,
        self::COLUMN_ORDER          => 'INT(11) UNSIGNED NOT NULL DEFAULT 1000',
        self::COLUMN_CREATED        => 'DATETIME NOT NULL',
        self::COLUMN_UPDATED        => 'DATETIME DEFAULT NULL',
    ];
    //
    protected $indexKeys = [self::COLUMN_ISACTIVE, self::COLUMN_ORDER, 'phone_country', 'currency'];
    //
    protected $foreignKeys = [
        'language' => [
            'refTable' => self::TABLE_LANGUAGE,
            'refColumn'=>'code',
        ],
    ];
    //
    //
    // common table's info
    protected $insertColumns = [
        'code',
        self::COLUMN_NAME,
        'phone_country',
        'language',
        'currency',
        self::COLUMN_CREATED,
        self::COLUMN_UPDATED,
    ];
    //
    protected $insertRows = [
        ['UK',  'United Kingdom',   '44',   'EN', 'GBP'],
        ['USA', 'United States',    '1',    'EN', 'USD'],
        ['UA',  'Ukraine',          '380',  'RU', 'UAH'],
    ];

}
```

###Properties
- `$tableName` is a table name. REQUIRED property. You can use constants instead (see below).
- `$columns` is a list of columns. REQUIRED property. 'Created' and 'Updated' columns will be added automatically. 
- `$indexKeys` is a list of index keys. Use simple array if you want create simple index or use associative key (`'phone_country' => true`) if you need to create unique key. 
- `$foreignKeys` is a list of foreign keys. Use simple array like `foreignKeyThisTable => relatedTableName` to create relation with column named `id` in related table or you can use associative array instead.
- `$insertColumns` is a list of columns of insertion. Use it if you want insert some default data immediate after table creation. 
- `$insertRows` is a list of rows of insertion. Use it if you want insert some default data immediate after table creation.

You can use also some map interface to store table names in one place and use constants like self::TABLE_NAME 
```php
class m000000_000000_tbl_country extends Migration implements DbMapInterface
{
    // common table's info
    protected $tableName = self::TABLE_COUNTRY;
}
```

Also you can tune other options like:
- `$tableOptions`
- `$isAutoAppendDateTime`
- `$insertSingleColumnName`
- `$insertCreatedColumnName`
- `$insertUpdatedColumnName`
- `$insertStatusUpdatedColumnName`
