<?php
// Run: php tests/guest_knowledge.php [path/to/KnowledgeController.php]
namespace App\Http\Controllers { class Controller {} }
namespace App\Models { class User implements \ArrayAccess {
    public function offsetExists(mixed $offset): bool { return $offset === 'token'; }
    public function offsetGet(mixed $offset): mixed { return 'test-token'; }
    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}
} }
namespace App\Services { class UserService {
    public function __construct(private bool $available) {}
    public function isAvailable(\App\Models\User $user): bool { return $this->available; }
} }
namespace App\Utils { class Helper {
    public static function getSubscribeUrl(string $token): string { return 'https://example.invalid/' . $token; }
} }
namespace {
    function admin_setting($key, $default) { return $default; }
    function __($text) { return $text; }
    require $argv[1] ?? __DIR__ . '/../app/Http/Controllers/V1/User/KnowledgeController.php';
    $method = new \ReflectionMethod(\App\Http\Controllers\V1\User\KnowledgeController::class, 'processKnowledgeContent');
    $article = ['body' => 'public <!--access start-->restricted<!--access end--> {{subscribeUrl}} {{siteName}}'];
    foreach (['guest', 'expired', 'active'] as $mode) {
        $controller = new \App\Http\Controllers\V1\User\KnowledgeController(new \App\Services\UserService($mode === 'active'));
        $user = $mode === 'guest' ? null : new \App\Models\User();
        $body = $method->invoke($controller, $article, $user)['body'];
        if (str_contains($body, 'restricted') !== ($mode === 'active') ||
            str_contains($body, 'https://example.invalid/test-token') !== ($mode !== 'guest') ||
            str_contains($body, '{{') || !str_contains($body, 'public')) {
            throw new \RuntimeException('Knowledge access check failed: ' . $mode);
        }
    }
    echo "Guest, expired and active knowledge checks passed.\n";
}
