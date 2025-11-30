<?php
namespace Gibbon\Module\HousePoints\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * House Points Category Gateway
 *
 * @version v20
 * @since   v20
 */
class HousePointCategoryGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'hpCategory';
    private static $primaryKey = 'categoryID';
    private static $searchableColumns = [];

    

    
}
