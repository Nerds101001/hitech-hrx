<?php
$file = 'd:\live_server\routes\web.php';
$content = file_get_contents($file);

$tempRoute = <<<PHP
Route::get('list-depts-temp', function() {
    \$depts = \App\Models\Department::with(['users' => function(\$q) { \$q->where('status', 'active'); }])->get();
    \$out = "";
    foreach(\$depts as \$d) {
        \$out .= "### " . \$d->name . " (" . \$d->users->count() . " active users)\\n";
        foreach(\$d->users as \$u) {
            \$out .= "- " . \$u->first_name . " " . \$u->last_name . " (" . \$u->email . ")\\n";
        }
        \$out .= "\\n";
    }
    return response(\$out)->header('Content-Type', 'text/plain');
});

PHP;

if (strpos($content, 'list-depts-temp') === false) {
    $content .= "\n" . $tempRoute;
    file_put_contents($file, $content);
}

echo "Added temp route!\n";
