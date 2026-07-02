<?php
namespace app\common\model;

use app\BaseModel;

class ThirdUserMap extends BaseModel
{
    protected $pk = 'id';

    protected $json = ['tags', 'extra'];
    protected $jsonAssoc = true;
}
