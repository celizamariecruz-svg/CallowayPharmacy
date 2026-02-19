#!/usr/bin/env php
<?php
/**
 * Calloway Pharmacy - Code Quality & Security Scanning Tool
 * 
 * Usage: php scan.php [options]
 * 
 * Options:
 *   --full       Run all checks
 *   --security   Security vulnerabilities only
 *   --quality    Code quality issues only  
 *   --format     Output format (text, json, html)
 *   --output     Save to file
 *   --exclude    Exclude patterns (comma-separated)
 */

class CodeScanner
{
    private $baseDir;
    private $excludeDirs = ['_deploy_bundle', 'vendor', 'node_modules', '.git'];
    private $findings = [];
    private $stats = [
        'critical' => 0,
        'high' => 0,
        'medium' => 0,
        'low' => 0,
        'files_scanned' => 0,
        'total_lines' => 0
    ];
    
    public function __construct($baseDir = '.')
    {
        $this->baseDir = rtrim($baseDir, '/');
    }
    
    /**
     * Run comprehensive code scanning
     */
    public function scan($options = [])
    {
        $scanType = $options['type'] ?? 'full';
        
        echo "\n🔍 Calloway Pharmacy Code Scanner\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        if (in_array($scanType, ['full', 'security'])) {
            echo "Scanning for Security Issues...\n";
            $this->scanSecurityIssues();
        }
        
        if (in_array($scanType, ['full', 'quality'])) {
            echo "Scanning for Code Quality Issues...\n";
            $this->scanCodeQuality();
        }
        
        echo "\nScanning for Best Practice Violations...\n";
        $this->scanBestPractices();
        
        return $this->generateReport($options['format'] ?? 'text');
    }
    
    /**
     * Scan for security vulnerabilities
     */
    private function scanSecurityIssues()
    {
        $patterns = [
            // SQL Injection patterns
            'sql_concat' => [
                'pattern' => '/\$\w+\s*\.=\s*"[^"]*\$\{?[\w\[\]\'\"]+\}?/',
                'severity' => 'HIGH',
                'message' => 'Potential SQL Injection: Dynamic SQL concatenation',
                'type' => 'SQL Injection'
            ],
            'unescaped_sql' => [
                'pattern' => '/\$\w+\s*=\s*"[^"]*"\s*\.\s*\$[\w\[\]\'\"]+/',
                'severity' => 'HIGH',
                'message' => 'String concatenation in SQL - use prepared statements',
                'type' => 'SQL Injection'
            ],
            
            // XSS patterns
            'unescaped_echo' => [
                'pattern' => '/echo\s+\$_(?:GET|POST|REQUEST|SERVER)/',
                'severity' => 'CRITICAL',
                'message' => 'Unescaped user/server input in output',
                'type' => 'XSS'
            ],
            'missing_htmlspecialchars' => [
                'pattern' => '/echo\s+\$(?!SESSION)[\w\[\]]+(?!;|htmlspecialchars)/',
                'severity' => 'MEDIUM',
                'message' => 'Variable output not escaped - may allow XSS',
                'type' => 'XSS'
            ],
            
            // Command Injection
            'shell_exec' => [
                'pattern' => '/\b(?:exec|shell_exec|system|passthru|proc_open)\s*\(/',
                'severity' => 'HIGH',
                'message' => 'Dangerous function: shell command execution',
                'type' => 'Command Injection'
            ],
            
            // Weak Crypto
            'md5_hash' => [
                'pattern' => '/md5\s*\(/',
                'severity' => 'MEDIUM',
                'message' => 'MD5 is cryptographically broken - use PASSWORD_BCRYPT',
                'type' => 'Weak Crypto'
            ],
            'base64_encode_password' => [
                'pattern' => '/base64_encode\s*\(\s*\$.*(?:password|pass|pwd|secret)/',
                'severity' => 'HIGH',
                'message' => 'Base64 is not encryption - use proper key encryption',
                'type' => 'Weak Crypto'
            ],
            
            // Hardcoded Secrets
            'hardcoded_api_key' => [
                'pattern' => '/(api_key|apiKey|API_KEY)\s*=\s*["\']([a-zA-Z0-9]{20,})["\']/',
                'severity' => 'CRITICAL',
                'message' => 'Hardcoded API key/secret found',
                'type' => 'Hardcoded Secret'
            ],
            'hardcoded_password' => [
                'pattern' => '/password\s*=\s*["\'](?!{)[^{"][^"\']*["\']/',
                'severity' => 'CRITICAL',
                'message' => 'Hardcoded password detected',
                'type' => 'Hardcoded Secret'
            ]
        ];
        
        $this->scanWithPatterns($patterns);
    }
    
    /**
     * Scan for code quality issues
     */
    private function scanCodeQuality()
    {
        $patterns = [
            'unused_variable' => [
                'pattern' => '/\$\w+\s*=\s*(?:new|require|include|file_get_contents|json_decode|explode|array_filter).*?;(?!.*\$\w+)/',
                'severity' => 'LOW',
                'message' => 'Unused variable assignment',
                'type' => 'Code Quality'
            ],
            'commented_code' => [
                'pattern' => '/\/\/\s*\$\w+|\/\/\s*if\s*\(|#\s+\$\w+/',
                'severity' => 'LOW',
                'message' => 'Commented-out code should be removed',
                'type' => 'Code Quality'
            ],
            'long_function' => [
                'pattern' => '/function\s+\w+\s*\([^)]*\)\s*\{[\s\S]{2000,}/',
                'severity' => 'MEDIUM',
                'message' => 'Function exceeds 2000 characters - consider refactoring',
                'type' => 'Code Quality'
            ],
            'multiple_nested_loops' => [
                'pattern' => '/foreach.*\{[\s\S]*foreach.*\{[\s\S]*foreach/',
                'severity' => 'MEDIUM',
                'message' => 'Triple-nested loops - potential performance issue',
                'type' => 'Code Quality'
            ],
            'missing_return_type' => [
                'pattern' => '/public\s+function\s+\w+\s*\([^)]*\)\s*\{/',
                'severity' => 'LOW',
                'message' => 'Function missing return type hint',
                'type' => 'Code Quality'
            ]
        ];
        
        $this->scanWithPatterns($patterns);
    }
    
    /**
     * Scan for best practice violations
     */
    private function scanBestPractices()
    {
        $patterns = [
            'no_error_check' => [
                'pattern' => '/\$\w+\s*=\s*(?:fopen|fread|json_decode|file_get_contents).*?;(?![\s]*if|[\s]*\}[\s]*else)/',
                'severity' => 'MEDIUM',
                'message' => 'Missing error handling for risky function',
                'type' => 'Best Practices'
            ],
            'global_variables' => [
                'pattern' => '/global\s+\$\w+/',
                'severity' => 'MEDIUM',
                'message' => 'Use of global variables - pass as parameters instead',
                'type' => 'Best Practices'
            ],
            'super_global_direct_use' => [
                'pattern' => '/\$_(?:GET|POST)\[[\s\S]*\](?!.*isset|!empty|filter_var)/',
                'severity' => 'MEDIUM',
                'message' => 'Direct use of $_GET/$_POST without validation',
                'type' => 'Best Practices'
            ],
            'no_prepared_statements' => [
                'pattern' => '/\$conn->query\s*\(\s*["\']SELECT[\s\S]*\$[\w\[\]]+/',
                'severity' => 'HIGH',
                'message' => 'SQL query with variable concatenation - use prepared statements',
                'type' => 'Best Practices'
            ]
        ];
        
        $this->scanWithPatterns($patterns);
    }
    
    /**
     * Scan files for given patterns
     */
    private function scanWithPatterns($patterns)
    {
        $files = $this->getPhpFiles();
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->stats['total_lines'] += substr_count($content, "\n");
            $this->stats['files_scanned']++;
            
            foreach ($patterns as $patternKey => $patternData) {
                if (preg_match($patternData['pattern'], $content, $matches)) {
                    // Count line number
                    $lineNum = substr_count($content, "\n", 0, strpos($content, $matches[0])) + 1;
                    
                    $this->addFinding([
                        'file' => $this->relativePath($file),
                        'line' => $lineNum,
                        'type' => $patternData['type'],
                        'severity' => $patternData['severity'],
                        'message' => $patternData['message'],
                        'code' => trim(substr($matches[0], 0, 80))
                    ]);
                    
                    $this->stats[strtolower($patternData['severity'])]++;
                }
            }
        }
    }
    
    /**
     * Get all PHP files to scan
     */
    private function getPhpFiles()
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Skip excluded directories
                $skip = false;
                foreach ($this->excludeDirs as $exclude) {
                    if (strpos($file->getPathname(), $exclude) !== false) {
                        $skip = true;
                        break;
                    }
                }
                
                if (!$skip) {
                    $files[] = $file->getPathname();
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Add a finding
     */
    private function addFinding($finding)
    {
        $this->findings[] = $finding;
    }
    
    /**
     * Get relative path
     */
    private function relativePath($path)
    {
        $relative = str_replace($this->baseDir . DIRECTORY_SEPARATOR, '', $path);
        return str_replace('\\', '/', $relative);
    }
    
    /**
     * Generate report in requested format
     */
    private function generateReport($format = 'text')
    {
        switch ($format) {
            case 'json':
                return $this->formatJSON();
            case 'html':
                return $this->formatHTML();
            default:
                return $this->formatText();
        }
    }
    
    /**
     * Format as plain text
     */
    private function formatText()
    {
        $output = "\n";
        $output .= "📊 SCAN RESULTS\n";
        $output .= str_repeat("=", 70) . "\n\n";
        
        $output .= "🔴 Critical: {$this->stats['critical']} | ";
        $output .= "🟠 High: {$this->stats['high']} | ";
        $output .= "🟡 Medium: {$this->stats['medium']} | ";
        $output .= "🟢 Low: {$this->stats['low']}\n\n";
        
        $output .= "Files Scanned: {$this->stats['files_scanned']} | ";
        $output .= "Lines: {$this->stats['total_lines']}\n\n";
        
        $output .= str_repeat("-", 70) . "\n\n";
        
        // Group findings by severity
        $bySeverity = [];
        foreach ($this->findings as $finding) {
            $severity = $finding['severity'];
            if (!isset($bySeverity[$severity])) {
                $bySeverity[$severity] = [];
            }
            $bySeverity[$severity][] = $finding;
        }
        
        $severityOrder = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];
        
        foreach ($severityOrder as $severity) {
            if (!isset($bySeverity[$severity])) continue;
            
            $icon = ['CRITICAL' => '🔴', 'HIGH' => '🟠', 'MEDIUM' => '🟡', 'LOW' => '🟢'][$severity];
            $output .= "\n{$icon} {$severity} SEVERITY ISSUES\n";
            $output .= str_repeat("-", 70) . "\n";
            
            foreach ($bySeverity[$severity] as $finding) {
                $output .= "\n📄 {$finding['file']}:{$finding['line']}\n";
                $output .= "   Type: {$finding['type']}\n";
                $output .= "   Issue: {$finding['message']}\n";
                if (!empty($finding['code'])) {
                    $output .= "   Code: " . substr($finding['code'], 0, 60) . "...\n";
                }
            }
        }
        
        $output .= "\n" . str_repeat("=", 70) . "\n";
        $output .= "✅ Scan complete. See SECURITY_AUDIT_REPORT.md for detailed findings.\n\n";
        
        return $output;
    }
    
    /**
     * Format as JSON
     */
    private function formatJSON()
    {
        return json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'stats' => $this->stats,
            'findings' => $this->findings
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Format as HTML
     */
    private function formatHTML()
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calloway Pharmacy - Security Scan Report</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 20px; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .stats { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .stat-box { text-align: center; border-left: 4px solid #ddd; padding: 15px; }
        .critical { border-left-color: #dc3545; color: #dc3545; }
        .high { border-left-color: #fd7e14; color: #fd7e14; }
        .medium { border-left-color: #ffc107; color: #ffc107; }
        .low { border-left-color: #28a745; color: #28a745; }
        .findings { background: white; border-radius: 8px; overflow: hidden; }
        .finding { border-bottom: 1px solid #eee; padding: 20px; }
        .finding:last-child { border-bottom: none; }
        .severity-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 12px; margin-right: 10px; }
        .badge-critical { background: #dc3545; color: white; }
        .badge-high { background: #fd7e14; color: white; }
        .badge-medium { background: #ffc107; color: #333; }
        .badge-low { background: #28a745; color: white; }
        .file-link { font-family: monospace; color: #667eea; font-weight: 500; }
        .message { margin: 10px 0; }
        .code { background: #f8f8f8; padding: 10px; border-left: 3px solid #667eea; font-family: monospace; font-size: 12px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔍 Calloway Pharmacy Security Scan Report</h1>
        <p>Generated: {$this->formatDateTime()}</p>
    </div>
    
    <div class="stats">
        <div class="stat-box critical">
            <div style="font-size: 28px; font-weight: bold;">{$this->stats['critical']}</div>
            <div>Critical Issues</div>
        </div>
        <div class="stat-box high">
            <div style="font-size: 28px; font-weight: bold;">{$this->stats['high']}</div>
            <div>High Issues</div>
        </div>
        <div class="stat-box medium">
            <div style="font-size: 28px; font-weight: bold;">{$this->stats['medium']}</div>
            <div>Medium Issues</div>
        </div>
        <div class="stat-box low">
            <div style="font-size: 28px; font-weight: bold;">{$this->stats['low']}</div>
            <div>Low Issues</div>
        </div>
    </div>
    
    <div class="stats">
        <div class="stat-box" style="grid-column: 1/3; border-left-color: #667eea; color: #667eea;">
            <div style="font-size: 24px;">{$this->stats['files_scanned']}</div>
            <div>Files Scanned</div>
        </div>
        <div class="stat-box" style="grid-column: 3/5; border-left-color: #667eea; color: #667eea;">
            <div style="font-size: 24px;">{$this->stats['total_lines']}</div>
            <div>Lines of Code</div>
        </div>
    </div>
    
    <div class="findings">
HTML;
        
        foreach ($this->findings as $finding) {
            $badgeClass = 'badge-' . strtolower($finding['severity']);
            $html .= <<<HTML
        <div class="finding">
            <div>
                <span class="severity-badge {$badgeClass}">{$finding['severity']}</span>
                <span class="file-link">{$finding['file']}:{$finding['line']}</span>
            </div>
            <div class="message"><strong>{$finding['type']}</strong></div>
            <div class="message">{$finding['message']}</div>
            {$this->getCodeHtml($finding['code'])}
        </div>
HTML;
        }
        
        $html .= <<<HTML
    </div>
    
    <footer style="text-align: center; margin-top: 40px; color: #999; font-size: 12px;">
        <p>Full audit report available in SECURITY_AUDIT_REPORT.md</p>
    </footer>
</body>
</html>
HTML;
        
        return $html;
    }
    
    private function getCodeHtml($code)
    {
        return $code ? "<div class=\"code\">" . htmlspecialchars($code) . "</div>" : '';
    }
    
    private function formatDateTime()
    {
        return date('Y-m-d H:i:s');
    }
}

// CLI Entry Point
if (php_sapi_name() === 'cli') {
    $options = ['type' => 'full', 'format' => 'text', 'output' => null];
    
    for ($i = 1; $i < $argc; $i++) {
        if ($argv[$i] === '--security') {
            $options['type'] = 'security';
        } elseif ($argv[$i] === '--quality') {
            $options['type'] = 'quality';
        } elseif ($argv[$i] === '--json') {
            $options['format'] = 'json';
        } elseif ($argv[$i] === '--html') {
            $options['format'] = 'html';
        } elseif ($argv[$i] === '--output' && isset($argv[$i + 1])) {
            $options['output'] = $argv[++$i];
        }
    }
    
    $scanner = new CodeScanner(getcwd());
    $report = $scanner->scan($options);
    
    if ($options['output']) {
        file_put_contents($options['output'], $report);
        echo "Report saved to: {$options['output']}\n";
    } else {
        echo $report;
    }
}
?>
