# ![Managur](https://static.managur.com/images/managur_logo.png)<br>Security Policy

## Supported Versions

We actively support the following versions of Rick-Role with security updates:

| Version | Supported |
| ------- |-----------|
| 1.x.x   | ✅        |

## Reporting a Vulnerability

The Managur team takes security vulnerabilities seriously. We appreciate your efforts to responsibly disclose your
findings, and will make every effort to acknowledge your contributions.

### How to Report a Security Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report security vulnerabilities by emailing: **security@managur.io**

Include the following information in your report:

- Type of issue (e.g., SQL injection, cross-site scripting, etc.)
- Full paths of source file(s) related to the manifestation of the issue
- The location of the affected source code (tag/branch/commit or direct URL)
- Any special configuration required to reproduce the issue
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit the issue

This information will help us triage your report more quickly.

### What to Expect

- **Acknowledgment**: We'll acknowledge receipt of your vulnerability report within 72 hours
- **Initial Assessment**: We'll provide an initial assessment within 7 days
- **Regular Updates**: We'll keep you informed of our progress throughout the investigation
- **Resolution Timeline**: We aim to resolve critical vulnerabilities within 7 days, high severity within 14 days
- **Disclosure**: We'll work with you to determine an appropriate disclosure timeline

### Security Response Process

1. **Report received and acknowledged**
2. **Issue confirmed and assessed for impact**
3. **Fix developed and tested in Docker environment**
4. **Security advisory prepared**
5. **Fix released**
6. **Public disclosure (coordinated with reporter)**

## Security Considerations for Users

### Authentication vs Authorization

Rick-Role is an **authorization** library, not an authentication library. It assumes you have already authenticated your
users through your application's authentication system. Always ensure:

- Users are properly authenticated before checking permissions
- User IDs passed to Rick-Role methods are validated and trusted
- Session management is handled securely by your application

### Database Security

- Use strong, unique passwords for database connections
- Enable SSL/TLS for database connections in production
- Regularly update your database engine and drivers
- Follow the principle of least privilege for database user accounts
- Consider encrypting sensitive data at rest

### Permission Design

- Design permissions with the principle of least privilege
- Avoid overly broad permissions (e.g., "admin" that grants everything)
- Regularly audit and review assigned roles and permissions
- Consider implementing time-based or conditional permissions for sensitive operations

### Input Validation

While Rick-Role performs basic type checking, you should:

- Validate and sanitize all user inputs before passing to Rick-Role
- Use proper encoding when displaying permission-related data
- Be cautious with user-controlled permission strings and subjects

### Logging and Monitoring

Rick-Role provides comprehensive audit logging through PSR-3 compatible loggers. Configure logging to monitor permission
decisions:

- **Log all permission checks** for audit purposes using `setLogger()`
- **Use appropriate log levels**: INFO for production monitoring, DEBUG for troubleshooting
- **Monitor for unusual permission patterns** and repeated denials
- **Include permission context** in your application logs
- **Set up alerts** for repeated permission denials or suspicious patterns

**Example Logging Setup:**

```php
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;

// Production logging setup
$logger = new Logger('rick-role');

// Rotating file handler for audit logs
$logger->pushHandler(new RotatingFileHandler(
    __DIR__ . '/logs/rick-role-audit.log',
    90, // Keep for 90 days
    Logger::INFO
));

// Console output for development
if ($app->environment('local')) {
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
}

$config = new Configuration();
$config->setLogger($logger);
```

**Security Monitoring:**

- Monitor WARNING level logs for denied permissions
- Set up alerts for unusual access patterns
- Log correlation IDs to trace user sessions
- Include IP addresses and user agents in log context
- **Always run security tests in Docker for consistency**

### Development Security

- Never commit database credentials or API keys
- Use environment variables for sensitive configuration
- Keep development and production data separate
- Regularly update dependencies and check for known vulnerabilities
- **Always run security tests in Docker for consistency**

## Vulnerability Disclosure Policy

We believe in responsible disclosure and will:

- Work with security researchers to address vulnerabilities
- Provide credit to researchers who report vulnerabilities (if desired)
- Maintain clear communication throughout the process
- Release security advisories for confirmed vulnerabilities

## Security Resources

- [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)
- [PHP Security Guide](https://phpsecurity.readthedocs.io/en/latest/)
- [Doctrine Security](https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/security.html)

## Security-Related Configuration

### Recommended Production Settings

```php
// Recommended PHP settings for production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);

// Rick-Role security configuration with audit logging
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

$logger = new Logger('rick-role');
$logger->pushHandler(new RotatingFileHandler(
    __DIR__ . '/logs/rick-role-audit.log',
    90, // Keep for 90 days
    Logger::INFO
));

$config = new Configuration();
$config->setLogger($logger);
```

## Contact

For security-related questions or concerns, please contact:

- Email: security@managur.io
- Website: https://managur.io
- For general questions: Open a GitHub issue (non-security related only)

---

Thank you for helping keep Rick-Role and its users safe!