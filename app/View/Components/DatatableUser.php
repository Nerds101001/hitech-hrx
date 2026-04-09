<?php

namespace App\View\Components;

use Illuminate\View\Component;

class DatatableUser extends Component
{
    public $user;
    public $showCode;
    public $linkRoute;
    public $avatarSize;

    /**
     * Create a new component instance.
     *
     * @param mixed $user
     * @param bool $showCode
     * @param string $linkRoute
     * @param string $avatarSize
     * @return void
     */
    public function __construct($user, $showCode = true, $linkRoute = 'employees.show', $avatarSize = 'sm')
    {
        $this->user = $user;
        $this->showCode = $showCode;
        $this->linkRoute = $linkRoute;
        $this->avatarSize = $avatarSize;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.datatable-user');
    }
}