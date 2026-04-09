<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Breadcrumb extends Component
{
    /**
     * The page title.
     *
     * @var string
     */
    public $title;

    /**
     * The breadcrumb items.
     *
     * @var array
     */
    public $breadcrumbs;

    /**
     * The home URL.
     *
     * @var string|null
     */
    public $homeUrl;

    /**
     * Create a new component instance.
     *
     * @param string $title
     * @param array $breadcrumbs
     * @param string|null $homeUrl
     * @return void
     */
    public function __construct($title, $breadcrumbs = [], $homeUrl = null)
    {
        $this->title = $title;
        $this->breadcrumbs = $breadcrumbs;
        $this->homeUrl = $homeUrl;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.breadcrumb');
    }
}