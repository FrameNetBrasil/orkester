<?php

namespace Orkester\Types;

class MRange
{

    public $page;
    public $offset;
    public $rows;
    public $total;

    public function __construct(int $page, int $rows, int $total = 0)
    {
        $this->page = $page ?? 1;
        $this->offset = ($this->page - 1) * $rows;
        $this->rows = ($total ? ((($this->offset + $rows) > $total) ? $total - $this->offset : $rows) : $rows);
        $this->total = $total;
    }

    public static function fromOffset(int $offset, int $rows): MRange
    {
        $instance = new MRange(0, 0);
        $instance->offset = $offset;
        $instance->rows = $rows;
        return $instance;
    }
}
