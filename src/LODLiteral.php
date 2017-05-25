<?php
namespace res\liblod;

use res\liblod\LODTerm;

class LODLiteral extends LODTerm
{
    public $datatype = NULL;
    public $language = NULL;

    public function __construct($value, $spec)
    {
        parent::__construct($value);

        if(isset($spec['lang']))
        {
            $this->language = $spec['lang'];
        }
        else if(isset($spec['datatype']))
        {
            $this->datatype = $spec['datatype'];
        }
    }

    public function isResource()
    {
        return FALSE;
    }
}
