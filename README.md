Pre-install Checks

- Using PHP >= 5.3
- Using Magento >= 1.7

Installation

Clone a copy of this repository

- git clone https://github.com/Localexpress/localexpress-magento

Copy the content of skepr-shipping-magento/app to location_of_magento/app

NOTE: make sure you do not replace the directory, but append its contents.

Clear Magento cache

- Disable cache management: Magento Backend > Cache Management > Cache Storage Management (disable all)
- Flush cache: Magento Backend > System > Cache Management > Flush Magento Cache
- Empty directory: 'magento/var/cache'
- Logout/ login of backend

Verify Order Skepr ID (sometimes auto-install does not run query for 'order.skepr_oid')

- Login to your favorite SQL tool (ex. phpmyadmin)
    - Lookup table details about: 'sales_flat_order'
        - Verify the existence of: 'skepr_oid' VARCHAR 128, and create if it's missing

Configuration
Shipping Method
This will add shipping method to the checkout process.

- Login to your Magento backend (magento_server/index.php/admin)
- Go to the Configuration screen (System tab)
- Go to the Shipping Method section (Sales submenu on the left)
- Lookup The Skepr Shipping method
- Enter your skepr username in the input field
- Enter your skepr password in the password field
- Target the server input field to: https://api.localexpress.nl/ (default setting)
- Select 'Default product category' (category for Product add via API)
- Select 'Default product store' (store for Product add via API)
- Select 'Default product attribute set' (attribute set for Product add via API)
- Save the configuration (save config button at the top right)

Admin Theme
By default comment and tracking escape all special characters. Order comments and tracking details link to Skepr. This theme will activate them (backend only).

- Login to your Magento backend (magento_server/index.php/admin)
- Go to the Configuration screen (System tab)
- Go to the Design section (General submenu on the left)
- Lookup Admin Theme
- Enter 'skepr' into Admin theme name input field
- Save the configuration (save config button at the top right)

Frontend Theme
By default comment and tracking escape all special characters. Order comments and tracking details link to Skepr. This theme will activate them (frontend only).

- Login to your Magento backend (magento_server/index.php/admin)
- Go to the Design screen (System tab)
- Create new design (add design change button, top right)
- Select 'skepr' in the drop-down for Custom Design
- Save the configuration (save button at the top right)

Verification and Troubleshooting
Make sure in the Skepr Shipping screen field test contains: 'Connected successfully!', if not there will be an error message. You cannot use the shipping method if it is not connected successfully.

For a successful connection, Skepr requires the correct username, password and server configuration.
