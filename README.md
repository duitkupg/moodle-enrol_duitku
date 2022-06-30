# Moodle Payment Gateway Duitku

Welcome to the Duitku plugin repository for Moodle. This plugin is a payment as enrolment in moodle. As direction from moodle documentation about plugin type enrol. Duitku attend a plugin to help you receive payment through Duitku.

## Steps you need to Integrate
1. Download and install the plugin.
2. If you haven't Duitku account then you need to register.
3. Grab your Duitku API key and merchant code.
4. Configure the Moodle enrolment with Duitku payment.
5. Add 'Duitku Payment' to the Moodle courses that you want

### Installation
After you download the plugin.
1. First, you need to login as admin to your moodle site.
2. Then, go to **Site administration** -> **Plugins** -> **Install plugins**
3. You'll see the choose file button or you can drag and drop the plugin zip file to the box. Choose or drop the zip file plugin.
4. Then, click **install plugin from ZIP file**.
5. Then, click **continue** after installation complete.

### Create Duitku Account
> To create an account you may see it [here](https://docs.duitku.com/account/).

### Configure Duitku payment as enrolment method
1. For the configuration, go to **Site administration** -> **Plugins** -> **Enrolments** -> **Manage enrol plugins**.
2. You should found **Duitku Payment** on the list. Make sure it is enable.
7. Input **API key**, **merchant code**, and **expiry period** of your desired value.
8. Then don't forget to set it in the right **environment**.
5. You can configure enrolment setting within **Duitku Payment**.

>***Please note, if you set wrong environment the access would be denied on payment.*

### Add Duitku payment
1. Go to course that you desired to add a payment.
2. On inside the course go to **participants**.
3. On the **participants** page, click the actions menu and select **Enrolment methods**.
4. Choose **Duitku Payment** from the Add dropdown menu.
5. You can set cost for the course on the **Enrol cost** then click the button **Add method**.

## Details

Duitku offers payment in Rupiah currencies that supported with virtual accounts, QRIS, paylater, e-wallet, retail outlets and credit card around Indonesia.
You might visit our website at [www.duitku.com](https://www.duitku.com/) for further information.