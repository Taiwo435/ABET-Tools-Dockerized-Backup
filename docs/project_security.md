# Project Security Updates

This document outlines security findings identified using OWASP ZAP, along with the fixes implemented so far.
The complete ZAP report can be downloaded here: 
https://github.com/hoang-danny05/ABET-Tools-Dockerized/actions/runs/23509389049

## Summary of Alerts

| Risk Level | Number of Alerts |
| --- | --- |
| High | 0 |
| Medium | 7 |
| Low | 8 |
| Informational | 9 |

## Identified Findings
The following table summarizes all findings reported by ZAP during the scan.

| Name | Risk Level | Number of Instances | Status |
| --- | --- | --- | --- |
| Absence of Anti-CSRF Tokens | Medium | 2 | In Progress |
| Anti-CSRF Tokens Check | Medium | 2 | In Progress |
| Bypassing 403 | Medium | 4 | In Progress |
| Content Security Policy (CSP) Header Not Set | Medium | Systemic | Fixed |
| Missing Anti-clickjacking Header | Medium | Systemic | Fixed |
| Proxy Disclosure | Medium | Systemic | In Progress |
| Relative Path Confusion | Medium | 2 | Fixed |
| Big Redirect Detected (Potential Sensitive Information Leak) | Low | 2 | Addressed |
| Cookie without SameSite Attribute | Low | 1 | Not Addressed |
| Cross-Origin-Embedder-Policy Header Missing or Invalid | Low | 3 | Not Addressed |
| Cross-Origin-Opener-Policy Header Missing or Invalid | Low | 3 | In Progress |
| Cross-Origin-Resource-Policy Header Missing or Invalid | Low | 5 | In Progress |
| Permissions Policy Header Not Set | Low | Systemic | Fixed |
| Strict-Transport-Security Header Not Set | Low | Systemic | Partially Fixed |
| X-Content-Type-Options Header Missing | Low | Systemic | Fixed |
| Authentication Request Identified | Informational | 1 | Not Addressed |
| Cookie Slack Detector | Informational | Systemic | Not Addressed |
| Modern Web Application | Informational | 3 | Not Addressed |
| Non-Storable Content | Informational | Systemic | Fixed |
| Re-examine Cache-control Directives | Informational | 1 | Partially Fixed |
| Session Management Response Identified | Informational | 2 | Not Addressed |
| Storable and Cacheable Content | Informational | 2 | Partially Fixed |
| User Agent Fuzzer | Informational | Systemic | Not Addressed |
| User Controllable HTML Element Attribute (Potential XSS) | Informational | 4 | Not Addressed |

## Remediation Summary

The following sections describe the key issues addressed and the fixes implemented.

### Absence of Anti-CSRF Tokens
- In progress
### Anti-CSRF Tokens Check
- In progress
### Bypassing 403
- In progress
### Content Security Policy (CSP) Header Not Set
- Implemented security_headers.php to restrict resource loading to trusted sources and reduce XSS risk
### Missing Anti-clickjacking Header
- Added X-Frame-Options (DENY) in security_headers.php to prevent the application from being embedded in iframes
### Proxy Disclosure
- In progress
### Relative Path Confusion
- Updated forgot_password.php and forgot_password_sent.php
- Replaced relative URLs and empty form actions with root-relative paths
- Added <!doctype html> to ensure consistent browser rendering
### Big Redirect Detected (Potential Sensitive Information Leak)
- Investigated redirect behavior in PHP and Apache configuration
- Determined redirects originate from .htaccess rewrite rules
- Confirmed no sensitive data is exposed in redirect responses
### Cookie without SameSite Attribute
- Not addressed (Low)
### Cross-Origin-Embedder-Policy Header Missing or Invalid
- Not addressed (Low)
### Cross-Origin-Opener-Policy Header Missing or Invalid
- In progress
### Cross-Origin-Resource-Policy Header Missing or Invalid
- In progress
### Permissions Policy Header Not Set
- Added Permissions-Policy header to security_headers.php to disable access to sensitive browser features (camera, microphone, geolocation)
### Strict-Transport-Security Header Not Set
- HSTS header added in security_headers.php but currently disabled
- Intended to be enabled in production environments over HTTPS only
### X-Content-Type-Options Header Missing
- Added X-Content-Type-Options: nosniff to prevent MIME-type sniffing and reduce content-based attacks
### Authentication Request Identified
- Not addressed (Informational)
### Cookie Slack Detector
- Not addressed (Informational)
### Modern Web Application
- Not addressed (Informational)
### Non-Storable Content
- Updated .htaccess to allow caching for static assets (CSS, JS, images) while retaining no-store for dynamic content
### Re-examine Cache-control Directives
- Partially addressed through updated caching rules in .htaccess
### Session Management Response Identified
- Not addressed (Informational)
### Storable and Cacheable Content
- Partially addressed through updated caching rules in .htaccess
### User Agent Fuzzer
- Not addressed (Informational)
### User Controllable HTML Element Attribute (Potential XSS)
- Not addressed (Informational)

