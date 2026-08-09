<?php

namespace App\View\Components;

use Illuminate\View\Component;

class CompanyConfig extends Component
{
    public $config;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->config = get_company_config();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.company-config');
    }
}

