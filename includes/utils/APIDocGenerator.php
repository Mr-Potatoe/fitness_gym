<?php
class APIDocGenerator {
    private $apiRoutes = [];
    private $basePath;
    private $version;

    public function __construct($basePath = '/api', $version = 'v1') {
        $this->basePath = rtrim($basePath, '/');
        $this->version = $version;
    }

    /**
     * Add an API endpoint documentation
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $params Parameters description
     * @param array $responses Possible responses
     * @param string $description Endpoint description
     * @param array $examples Example requests and responses
     */
    public function addEndpoint($method, $endpoint, $params = [], $responses = [], $description = '', $examples = []) {
        $this->apiRoutes[] = [
            'method' => strtoupper($method),
            'endpoint' => $endpoint,
            'params' => $params,
            'responses' => $responses,
            'description' => $description,
            'examples' => $examples
        ];
    }

    /**
     * Generate HTML documentation
     * @return string HTML documentation
     */
    public function generateHTML() {
        $html = $this->getDocumentationHeader();
        $html .= $this->generateEndpointsHTML();
        $html .= $this->getDocumentationFooter();
        return $html;
    }

    /**
     * Generate Markdown documentation
     * @return string Markdown documentation
     */
    public function generateMarkdown() {
        $md = "# API Documentation\n\n";
        $md .= "Base URL: `{$this->basePath}`\n";
        $md .= "API Version: {$this->version}\n\n";

        foreach ($this->apiRoutes as $route) {
            $md .= "## {$route['method']} {$route['endpoint']}\n\n";
            
            if ($route['description']) {
                $md .= "{$route['description']}\n\n";
            }

            if (!empty($route['params'])) {
                $md .= "### Parameters\n\n";
                $md .= "| Name | Type | Required | Description |\n";
                $md .= "|------|------|----------|-------------|\n";
                
                foreach ($route['params'] as $param) {
                    $required = $param['required'] ? 'Yes' : 'No';
                    $md .= "| {$param['name']} | {$param['type']} | {$required} | {$param['description']} |\n";
                }
                $md .= "\n";
            }

            if (!empty($route['responses'])) {
                $md .= "### Responses\n\n";
                foreach ($route['responses'] as $code => $response) {
                    $md .= "#### {$code}\n\n";
                    $md .= "{$response['description']}\n\n";
                    if (isset($response['schema'])) {
                        $md .= "```json\n{$response['schema']}\n```\n\n";
                    }
                }
            }

            if (!empty($route['examples'])) {
                $md .= "### Examples\n\n";
                foreach ($route['examples'] as $example) {
                    $md .= "#### Request\n\n";
                    $md .= "```json\n{$example['request']}\n```\n\n";
                    $md .= "#### Response\n\n";
                    $md .= "```json\n{$example['response']}\n```\n\n";
                }
            }

            $md .= "---\n\n";
        }

        return $md;
    }

    /**
     * Get HTML documentation header
     * @return string HTML header
     */
    private function getDocumentationHeader() {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - VikingsFit Gym</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .endpoint { margin: 2rem 0; padding: 1rem; border: 1px solid #ddd; border-radius: 4px; }
        .method { font-weight: bold; padding: 0.3rem 0.6rem; border-radius: 3px; }
        .method.get { background: #61affe; color: white; }
        .method.post { background: #49cc90; color: white; }
        .method.put { background: #fca130; color: white; }
        .method.delete { background: #f93e3e; color: white; }
        .endpoint-url { font-family: monospace; margin-left: 1rem; }
        .params-table { margin: 1rem 0; }
        .response-code { font-weight: bold; }
        .example-block { background: #f5f5f5; padding: 1rem; border-radius: 4px; }
        pre { background: #f8f8f8; padding: 1rem; overflow-x: auto; }
        .nav-wrapper { padding: 0 2rem; }
    </style>
</head>
<body>
    <nav class="blue darken-3">
        <div class="nav-wrapper">
            <a href="#" class="brand-logo">API Documentation</a>
            <ul id="nav-mobile" class="right hide-on-med-and-down">
                <li><span class="white-text">Version: {$this->version}</span></li>
            </ul>
        </div>
    </nav>
    <div class="container">
        <div class="row">
            <div class="col s12">
                <h4 class="header">Base URL: {$this->basePath}</h4>
HTML;
    }

    /**
     * Generate HTML for endpoints
     * @return string HTML content
     */
    private function generateEndpointsHTML() {
        $html = '';
        foreach ($this->apiRoutes as $route) {
            $methodClass = strtolower($route['method']);
            
            $html .= <<<HTML
                <div class="endpoint">
                    <div class="endpoint-header">
                        <span class="method {$methodClass}">{$route['method']}</span>
                        <span class="endpoint-url">{$route['endpoint']}</span>
                    </div>
HTML;

            if ($route['description']) {
                $html .= "<p>{$route['description']}</p>";
            }

            if (!empty($route['params'])) {
                $html .= <<<HTML
                    <h5>Parameters</h5>
                    <table class="params-table striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
HTML;

                foreach ($route['params'] as $param) {
                    $required = $param['required'] ? 'Yes' : 'No';
                    $html .= <<<HTML
                        <tr>
                            <td>{$param['name']}</td>
                            <td>{$param['type']}</td>
                            <td>{$required}</td>
                            <td>{$param['description']}</td>
                        </tr>
HTML;
                }

                $html .= "</tbody></table>";
            }

            if (!empty($route['responses'])) {
                $html .= "<h5>Responses</h5>";
                foreach ($route['responses'] as $code => $response) {
                    $html .= <<<HTML
                        <div class="response">
                            <span class="response-code">{$code}</span>
                            <p>{$response['description']}</p>
HTML;
                    
                    if (isset($response['schema'])) {
                        $html .= "<pre><code>{$response['schema']}</code></pre>";
                    }
                    
                    $html .= "</div>";
                }
            }

            if (!empty($route['examples'])) {
                $html .= "<h5>Examples</h5>";
                foreach ($route['examples'] as $example) {
                    $html .= <<<HTML
                        <div class="example-block">
                            <h6>Request</h6>
                            <pre><code>{$example['request']}</code></pre>
                            <h6>Response</h6>
                            <pre><code>{$example['response']}</code></pre>
                        </div>
HTML;
                }
            }

            $html .= "</div>";
        }
        return $html;
    }

    /**
     * Get HTML documentation footer
     * @return string HTML footer
     */
    private function getDocumentationFooter() {
        return <<<HTML
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
HTML;
    }
}
