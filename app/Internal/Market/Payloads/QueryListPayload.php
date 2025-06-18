<?php

namespace Internal\Market\Payloads;

use Illuminate\Http\Request;

class QueryListPayload {

    public $page = 1;
    public $pageSize = 15;
    public $isRecommend = 0;
    public $keyword = '';

    public function __construct(Request $request)
    {
        $this->page = $request->get('page',1);
        $this->pageSize = $request->get('page_size',15);
        $this->keyword = $request->get('keyword',null);
        $this->isRecommend = $request->get('is_recommend',0);
    }
}
