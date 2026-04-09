<?php

namespace App\View\Components;

use Illuminate\View\Component;

class DatatableActions extends Component
{
    public $id;
    public $actions;

    /**
     * Create a new component instance.
     *
     * @param mixed $id
     * @param array $actions
     * @return void
     */
    public function __construct($id, $actions = [])
    {
        $this->id = $id;
        $this->actions = $actions;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.datatable-actions');
    }
}