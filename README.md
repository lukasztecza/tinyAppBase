# tinyAppBase
Minimal application skeleton based on middleware, dependancy injection and model-view-controller patterns.

### Application flow
- application expects `APP_ROOT_DIR` to be defined as application root directory `/`
- note that web root directory should be in `/public`
- `TinyAppBase\Model\System\Project` should be the first class hit by app
- it will pull configurations from `src/Config/*` json files
- application sets error handler `TinyAppBase\Model\System\ErrorHandler`
- and builds `TinyAppBase\Model\System\Request` using `TinyAppBase\Model\System\Router`
- router determines `%routedController%` and `%routedAction%` parameters
- then first application middleware is executed named in `/src/Config/settings.json` as `applicationStartingPoint`
- this class should be specified in `src/Config/dependencies.json`

### Basic usage
- assuming that your app is `myRepo/myApp` then include using composer:
```json
{
    "name": "myRepo/myApp",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/lukasztecza/tinyAppBase"
       }
    ],
    "require": {
        "lukasztecza/tinyAppBase": "dev-master"
    },
    "autoload": {
        "psr-4": { "MyApp\\": "src/" }
    }
}
```
- create front controller `/public/app.php` (where your domain should point to) with the following content:
```php
<?php
define('APP_ROOT_DIR', str_replace('/public', '', __DIR__));
include(APP_ROOT_DIR . '/vendor/autoload.php');
(new TinyAppBase\Model\System\Project())->run();
```
- create `/.gitignore` with the following content:
```bash
src/Config/parameters.json

```
- create `/src/Config/parameters.json` which should contain sensitive data with the following content:
```json
{
    "environment": "dev"
}
```
- create `/src/Config/settings.json` which should contain all other configurations with the following content:
```json
{
    "defaultContentType": "application/json",
    "applicationStartingPoint": "simpleOutputMiddleware"
}
```
- create `/src/Config/routes.json` with the following content:
```json
[
    {
        "path": "/home",
        "methods": ["GET"],
        "controller": "myController",
        "action": "home"
    }
]
```
- create `/src/Config/dependencies.json` with the following content:
```json
{
    "simpleOutputMiddleware": {
        "class": "TinyAppBase\\Model\\Middleware\\SimpleOutputMiddleware",
        "inject": [
            "@controllerMiddleware@",
            "%defaultContentType%"
        ]
    },
    "controllerMiddleware": {
        "class": "TinyAppBase\\Model\\Middleware\\ControllerMiddleware",
        "inject": [
            "%routedController%",
            "%routedAction%"
        ]
    },
    "myController": {
        "class": "MyApp\\Controller\\MyController"
    }
}
```
- wrap in `@` to inject other class to your class constructor
- wrap in `%` to inject parameter specified in `src/Config/parameters.json` or `src/Config/settings.json`
- create `/src/Controller/MyController.php` with the following content:
```php
<?php
namespace MyApp\Controller;

use TinyAppBase\Controller\ControllerInterface;
use TinyAppBase\Model\System\Request;
use TinyAppBase\Model\System\Response;

class MyController implements ControllerInterface
{
    public function home(Request $request) : Response
    {
        return new Response(null, ['message' => 'Hello world!'], ['message' => 'raw']);
    }
}
```
- you can start php server in `/public` directory
```bash
cd /public
php -S localhost:8080
```
- and visit in browser
```bash
localhost:8080/app.php/home
```
- if you want to make use of error handler change in `/src/Config/parameters.json`:
```json
{
    "environment": "prod"
}
```
- it will store logs in `/tmp/logs/php-{date}.log` file instead of throwing errors to output and display error page

### Html output
- if you want to use `text/html` by default change in `/src/Config/settings.json`:
```json
{
   "defaultContentType": "text/html"
}
```
- create `/src/View/home.php` with the following content:
```html
<h3>
    My page
</h3>
<p><?php echo $message; ?></p>
```
- update `/src/Controller/MyController` to return response with specified filename:
```php
return new Response('home.php', ['message' => 'Hello world!'], ['message' => 'html']);
```
- if you do not want to change default content type you can also just set content type in the response object:
```php
return new Response('home.php', ['message' => 'Hello world!'], ['message' => 'html'], ['Content-Type' => 'text/html']);
```
### Running commands
- if you want to run command line jobs create `/scripts/command.php` with the following content:
```php
<?php
define('APP_ROOT_DIR', str_replace('/scripts', '', __DIR__));
include(APP_ROOT_DIR . '/vendor/autoload.php');
if (empty($argv[1])) {
    echo 'Please specify command object name from dependencies as parameter' . PHP_EOL;
    exit;
}
echo (new TinyAppBase\Model\System\Project())->runCommand($argv[1]);
```
- include in `/src/Config/dependencies.json` an entry for it:
```json
{
    "myCommand": {
        "class": "MyApp\\Model\\Command\\MyCommand",
        "inject": [
            "some variable"
        ]
    }
}
```
- create `/src/Model/Command/MyCommand` with the following content:
```php
<?php
namespace MyApp\Model\Command;

use TinyAppBase\Model\Command\CommandInterface;
use TinyAppBase\Model\Command\CommandResult;

class MyCommand implements CommandInterface
{
    private $someVariable;

    public function __construct(string $someVariable)
    {
        $this->someVariable = $someVariable;
    }

    public function execute() : CommandResult
    {
        echo 'Passed variable is: ' . $this->someVariable . PHP_EOL;
        return new CommandResult('success', 'everything went well');
    }
}
```
- go to `/scripts` and run:
```bash
php command.php myCommand
```
### Running with apache
- if you have set apache you may find this `/public/.htaccess` content useful:
```apacheconf
# Do not allow to index directory
Options -Indexes

# Set browser caching
<IfModule mod_headers.c>
    <FilesMatch "\.(jpg|jpeg|png|gif)$">
        Header set Cache-Control "max-age=86400, public"
    </FilesMatch>
    <FilesMatch "\.(css|js)$">
        Header set Cache-Control "max-age=86400, private"
    </FilesMatch>
    <FilesMatch "\.(ttf|woff|woff2|eot)$">
        Header set Cache-Control "max-age=86400, private"
    </FilesMatch>
</IfModule>

# Redirect to front controller
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Redirect to URI without front controller
    RewriteCond %{ENV:REDIRECT_STATUS} ^$
    RewriteRule ^app\.php(?:/(.*)|$) /$1 [R=301,L]

    # If the requested filename exists, simply serve it.
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ - [L]

    # Rewrite all other queries to the front controller.
    RewriteRule ^ /app.php [L]
</IfModule>

<IfModule !mod_rewrite.c>
    <IfModule mod_alias.c>
        # When mod_rewrite is not available redirect to the front controller
        RedirectMatch 302 ^/$ /app.php/
    </IfModule>
</IfModule>
```
### Restricting routes
- add securityMiddleware and modify simpleOutputMiddleware to set it as next (first injection) in `/src/Config/dependencies.json`:
```
{
    "simpleOutputMiddleware": {
        "class": "TinyAppBase\\Model\\Middleware\\SimpleOutputMiddleware",
        "inject": [
            "@securityMiddleware@",
            "%defaultContentType%"
        ]
    },
    "securityMiddleware": {
        "class": "TinyAppBase\\Model\\Middleware\\SecurityMiddleware",
        "inject": [
            "@controllerMiddleware@",
            "%securityList%",
            "@sessionService@"
        ]
    }
}
```
- add in `/src/Config/settings.json`:
```json
{
    "securityList": [
        {
            "route": "/restricted",
            "methods": ["GET"],
            "allow": ["ROLE_USER"]
        }
    ]
}
```
- assign this route to a controller updating `/src/Config/routes/json`
```json
{
    "path": "/restricted",
    "methods": ["GET"],
    "controller": "myController",
    "action": "restricted"
}
```
- add corresponding method in `/src/Controller/MyController.php`:
```php
public function restricted(Request $request) : Response
{
    return new Response(null, ['message' => 'Restricted!'], ['message' => 'raw'], ['Content-Type' => 'application/json']);
}

```
- if user without `ROLE_USER` tries to navigate to `/restricted` then he will be redirected to `/login` so add in `/src/Config/routes/json`:
```json
[
    {
        "path": "/login",
        "methods": ["GET", "POST"],
        "controller": "authenticationController",
        "action": "login"
    },
    {
        "path": "/logout",
        "methods": ["GET"],
        "controller": "authenticationController",
        "action": "logout"
    }
]
```
- specify this controller along with session service and validator factory in `/src/Config/dependencies.json`:
```json
{
    "authenticationController": {
        "class": "TinyAppBase\\Controller\\AuthenticationController",
        "inject": [
            "@sessionService@",
            "@validatorFactory@",
            "%inMemoryUsername%",
            "%inMemoryPasswordHash%"
        ]
    },
    "sessionService": {
        "class": "TinyAppBase\\Model\\Service\\SessionService"
    },
    "validatorFactory": {
        "class": "TinyAppBase\\Model\\Validator\\ValidatorFactory",
        "inject": [
            "@sessionService@"
        ]
    }
}
```
- specify username and password in `/src/Config/parameters.json`:
```
{
    "inMemoryUsername": "user",
    "inMemoryPasswordHash": "$2y$12$mHx7zh06OUGvBrOaoaTgsesPZrGcNbPXQLgea4P865hMOW7LOOwN2"
}
```
- create `/src/Controller/AuthenticationController.php` with the following content:
```php
<?php
namespace TinyAppBase\Controller;

use TinyAppBase\Controller\ControllerInterface;
use TinyAppBase\Model\Service\SessionService;
use TinyAppBase\Model\Validator\ValidatorFactory;
use TinyAppBase\Model\Validator\LoginValidator;
use TinyAppBase\Model\System\Request;
use TinyAppBase\Model\System\Response;

class AuthenticationController implements ControllerInterface
{
    private $sessionService;
    private $validatorFactory;
    private $inMemoryUsername;
    private $inMemoryPasswordHash;

    public function __construct(
        SessionService $sessionService,
        ValidatorFactory $validatorFactory,
        string $inMemoryUsername,
        string $inMemoryPasswordHash
    ) {
        $this->sessionService = $sessionService;
        $this->validatorFactory = $validatorFactory;
        $this->inMemoryUsername = $inMemoryUsername;
        $this->inMemoryPasswordHash = $inMemoryPasswordHash;
    }

    public function login(Request $request) : Response
    {
        if (!empty($this->sessionService->get(['user'])['user'])) {
            return new Response(null, [], [], ['Location' => '/home']);
        }

        $validator = $this->validatorFactory->create(LoginValidator::class);
        if ($request->getMethod() === 'POST') {
            if ($validator->check($request)) {
                $payload = $request->getPayload(['username', 'password']);
                if (
                    $this->inMemoryUsername === $payload['username'] &&
                    password_verify($payload['password'], $this->inMemoryPasswordHash)
                ) {
                    $this->sessionService->set(['roles' => ['ROLE_USER']]);
                    $this->sessionService->set(['user' => $payload['username']]);

                    return new Response(null, [], [], [
                        'Location' => ($this->sessionService->get(['previousNotAllowedPath'], true)['previousNotAllowedPath'] ?? '/home')
                    ]);
                }
                $error = 'Invalid credentials';
            }
        }

        return new Response(
            'authentication/loginForm.php',
            ['error' => $error ?? $validator->getError(), 'csrfToken' => $validator->getCsrfToken()],
            ['error' => 'html']
        );
    }

    public function logout(Request $request) : Response
    {
        // Logout user
        $this->sessionService->set(['roles' => null]);
        $this->sessionService->set(['user' => null]);
        $this->sessionService->destroy();

        return new Response(null, [], [], ['Location' => '/home']);
    }
}
```
- create `/src/Model/Validator/LoginValidator.php` with the following content:
```php
<?php
namespace TinyAppBase\Model\Validator;

use TinyAppBase\Model\System\Request;
use TinyAppBase\Model\Validator\RequestValidatorAbstract;

class LoginValidator extends RequestValidatorAbstract
{
    public function validate(Request $request) : bool
    {
        $payload = $request->getPayload(['username', 'password']);
        if (empty($payload['username']) || empty($payload['password'])) {
            $this->error = 'Fields username and password can not be empty';

            return false;
        }

        return true;
    }
}
```
