<?php
declare(strict_types=1);
namespace Theosche\RoomReservation;

class Router {
	private array $routes = [];
	public function add(string $method, string $path, string $controller, bool $admin=false, string $customAfterLoginRedirect="") {
		$path = $this->normalizePath($path);
		$this->routes[] = [
		  'path' => $path,
		  'method' => strtoupper($method),
		  'controller' => $controller,
		  'admin' => $admin,
		  'customAfterLoginRedirect' => $customAfterLoginRedirect,
		];
	}
	private function normalizePath(string $path): string {
		$path = trim($path, '/'); $path = "/{$path}/"; $path = preg_replace('#[/]{2,}#', '/', $path);
		return $path;
	}
	public function dispatch(string $path) {
		$path = $this->normalizePath($path);
		$method = strtoupper($_SERVER['REQUEST_METHOD']);
		foreach ($this->routes as $route) {
			if (
				!preg_match("#^{$route['path']}$#", $path) ||
				$route['method'] !== $method
			) {
				continue;
			}
			// [$class, $function] = $route['controller'];
			// $controllerInstance = new $class;
			// $controllerInstance->{$function}();
			if (!$route['admin']) {
				require $route['controller'];
				exit;
			} else {
				session_start();
				// Vérification de l'authentification administrateur
				if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
					if (!empty($customAfterLoginRedirect)) {
						$_SESSION['redirect_after_login'] = $customAfterLoginRedirect;
					} else {
						$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
						$_SESSION['redirect_after_login'] = $path . '?' . $query;
					}
					header('Location: login.php');
					exit;
				}
				require $route['controller'];
			}
			exit;
		}
		http_response_code(404);
		require __DIR__ . '/../views/404.html';
		die();
	} 
} 
?>