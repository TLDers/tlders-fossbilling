# TLDers Price Monitor for FOSSBilling and ClientFence

Compare the domain prices you charge with the cheapest registrar on the market for every TLD you sell, so you can see where you are overpriced or leaving margin on the table. Prices come from the [TLDers](https://www.tlders.com) API (145+ registrars, 1,100+ extensions, updated daily).

It adds a page under **System → TLDers Price Monitor** in the admin area:

| TLD | Your register | Market register | Diff | Your renew | Market renew | Diff |
|---|---|---|---|---|---|---|
| .com | $11.99 | $8.50 Porkbun | +41% | $11.99 | $10.00 Cloudflare | +20% |

Red means more than your threshold above the cheapest registrar; green means you are below it.

## Requirements

- FOSSBilling 0.8.9, the FOSSBilling development branch (`main`, tested at commit `b4b6bdb`, 2026-11-24) or ClientFence 1.0.62 (the versions it was tested on), PHP 8.3 or newer, and the `curl` extension
- A TLDers API key. A free key (100 requests a month) is enough: prices are cached for 24 hours and one refresh uses 3 requests. Get one at [tlders.com/developers](https://www.tlders.com/developers).

## Install

1. Download the zip from the [latest release](../../releases/latest) for your platform, or copy the folder from this repository:
   - FOSSBilling: `fossbilling/Tldersprices`
   - ClientFence: `clientfence/Tldersprices`
2. Upload it to the `modules/` folder of your installation, so you end up with `modules/Tldersprices/`.
3. In the admin area go to **System → Extensions**, find **TLDers Price Monitor** and click **Activate**.
4. Open **Extensions → TLDers Price Monitor → Settings** and enter your API key.
5. Open **System → TLDers Price Monitor**.

## Settings

- **TLDers API key**: stored encrypted in the module configuration.
- **Flag when above market by (%)**: default 25.
- **USD → your currency**: leave blank to use the USD rate from your currency settings. If your default currency is USD, no conversion is needed.

## Notes

- Compares 1-year register and renew prices. Prices you have not set (0) are shown as "—".
- Staff need the **View TLDers price comparison** permission, which you can grant under staff permissions.
- The module only reads your TLD prices; it never changes them.

## Packages

`./package.sh` builds three zips into `dist/`:

| Zip | Use |
|---|---|
| `tlders-price-monitor-fossbilling-<version>.zip` | Manual install on FOSSBilling 0.8.9 or `main` (unzip into `modules/`) |
| `tlders-price-monitor-clientfence-<version>.zip` | Manual install on ClientFence |
| `tlders-price-monitor-fossbilling-directory-<version>.zip` | For [extensions.fossbilling.org](https://extensions.fossbilling.org): files at the zip root and a manifest v1. The directory's auto-install only exists on FOSSBilling's development branch; on 0.8.9 use the manual zip. |

`python3 tools/check_directory_zip.py <zip>` pre-checks a zip against the directory's published rules (a reimplementation, not the official validator).

## Development

The two modules are generated from one source:

```sh
php build.php        # writes fossbilling/Tldersprices and clientfence/Tldersprices
php tests/CoreTest.php
```

`core/Core.php` is framework independent (API client, cache, comparison). `template/` holds the module files with placeholders for the few names that differ between FOSSBilling and ClientFence.

## More from TLDers

- [WordPress plugin](https://wordpress.org/plugins/tlders-domain-prices/) (free)
- [WHMCS addon](https://github.com/TLDers/tlders-whmcs) (free)
- [Codester shop](https://www.codester.com/tlders/shop) (PHP script and mobile apps)
- [All products](https://www.tlders.com/products)

## License

MIT. See [LICENSE](LICENSE).
