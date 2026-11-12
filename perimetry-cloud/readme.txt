=== Perimetry Cloud – Security Score ===
Contributors: perimetrycloud
Tags: security, dmarc, ssl, nis2, monitoring
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The security score of your domain, seen from the outside, in your dashboard. Free check, no account needed.

== Description ==

Perimetry Cloud checks your domain from the outside, the way an attacker would see it, and tells you in plain words what to fix:

* e-mail that anyone could send in your name (SPF, DKIM, DMARC),
* certificates about to expire or badly configured,
* exposed services and forgotten subdomains,
* look-alike domains registered to trick your customers,
* and, in WordPress itself: updates waiting, an unsupported PHP version, errors shown to visitors, file editing from the admin, XML-RPC, risky registration settings.

Nothing is installed on your server and nothing is attacked: Perimetry Cloud only reads what anyone can read on the internet.

**Free check, no account.** One click gives the score of your domain and its three most serious problems, in about two minutes.

**Free account.** Connect the site to see every finding with its fix, get a weekly audit and alerts, and prove that the domain is yours in one click from this site, without touching your DNS. Paid plans add daily audits, DMARC step by step, a remediation plan, reports and more: see [perimetry.cloud](https://perimetry.cloud/pricing/).

**For agencies.** Install it on every client site and connect them to your Perimetry Cloud workspace: one place for the security of all your clients.

The plugin is available in English, French and German.

== External services ==

This plugin connects to Perimetry Cloud (https://app.perimetry.cloud), a service run by Perimetry Cloud, to check the security of your domain from the outside. Nothing is sent until an administrator presses a button.

* **Free check** (button "Check my domain"): the address of this site is sent. Perimetry Cloud asks the site to show a one-time code at its own address (?perimetry_proof=…) to make sure the request comes from this site, then checks the domain's public records and services, and returns a score and the three most serious problems. The result is kept 7 days.
* **Connection** (button "Connect"): you sign in to Perimetry Cloud in your browser; the site receives a key that can only read the score and findings of its own domain.
* **Ownership proof** (button "Prove ownership with this site"): the site shows a code at its own address (?perimetry_verify=1); Perimetry Cloud reads it, then again once a day to make sure the site still proves the domain.
* **Results**: once connected, the plugin reads the score and findings of the domain when you open its page or the dashboard.
* **WordPress report** (only if you tick "Send this report to Perimetry Cloud once a day"): the versions of WordPress and PHP, the plugins and themes with their version, whether they are active and the update waiting, and a few settings (errors shown to visitors, file editing, XML-RPC, registration and its default role, HTTPS for the admin, multisite, the number of administrators, never who they are). No content, no user data, no visitor data.

Terms of service: https://perimetry.cloud/legal/terms/
Privacy policy: https://perimetry.cloud/legal/privacy/

== Installation ==

1. Install and activate the plugin.
2. Go to Settings › Perimetry Cloud, or look at the widget on your dashboard.
3. Press "Check my domain" for the free check, or "Connect" to see everything and be alerted.

== Frequently Asked Questions ==

= Does it slow my site down? =

No. Nothing runs for your visitors. The plugin only answers Perimetry Cloud's short checks at the home address, with one line of text, when one is expected.

= Does it scan or attack my site? =

No. Perimetry Cloud reads public information: DNS records, certificate logs, what your mail and web servers answer to any visitor, public data sets.

= Which sites can prove a domain? =

A site at the domain itself or at its www (example.com or www.example.com). A site at blog.example.com can run the free check, but proves the domain only with the DNS record.

= What if I remove the plugin? =

Everything it kept is deleted. Press "Disconnect" first to revoke its key at Perimetry Cloud; you can also disconnect it from the domain's page in Perimetry Cloud.

== Screenshots ==

1. The free check: score and the three most serious problems.
2. A connected site: every finding with its fix.
3. What WordPress itself shows.
4. The dashboard widget.

== Changelog ==

= 1.0.0 =
* First version: free check, connection, ownership proof by the site, findings with their fix, WordPress report with consent, dashboard widget, English, French and German.
