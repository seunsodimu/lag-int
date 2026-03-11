PayPal => NetSuite
invoice_number => transactionNumber
reference => tranId
invoice_date => tranDate
currency_code => "USD"
term => SELECT term FROM terms WHERE id = netsuite data {terms->id}
memo => memo
payment_term->term_type => "DUE_ON_DATE_SPECIFIED"
payment_term->due_date => (SELECT invoice_due_days FROM terms WHERE id = netsuite data {terms->id}) + tranDate (dddd-mm-dd)
invoicer->name->given_name =>"Laguna Tools"
invoicer->address->address_line_1 => "744 Refuge Way"
invoicer->address->address_line_2 => "Suite 200"
invoicer->address->admin_area_2 => "Grand Priaire"
invoicer->address->admin_area_1 => "TX"
invoicer->address->postal_code => "75050"
invoicer->country_code => "US"
invoicer->email_address" > "ar@lagunatools.com"
invoicer->phone->country_code => "001"
invoicer->phone->natioal_number => "8002341976"
primary_recipients->billing_info->name->given_name => custbody_ava_customerentityid
primary_recipients->billing_info->address->address_line_1 => billingAddress->addr1
primary_recipients->billing_info->address->address_line_2 => billingAddress-> addr2
primary_recipients->billing_info->address->admin_area_1 => billingAddress->state
primary_recipients->billing_info->address->admin_area_2 => billingAddress->city
primary_recipients->billing_info->address->postal_code => billingAddress->zip
primary_recipients->billing_info->address->country_code => billingAddress->country->id
primary_recipients->billing_info->email_address => email
primary_recipients->billing_info->phones->country_code=>"001" 
primary_recipients->billing_info->phones->national_number => billingAddress->addrPhone (strip "+1", "1" from beginning)

primary_recipients->shipping_info->name->given_name => shippingAddress->addressee
primary_recipients->shipping_info->address->address_line_1 => shippingAddress->addr1
primary_recipients->shipping_info->address->address_line_2 => shippingAddress-> addr2
primary_recipients->shipping_info->address->admin_area_1 => shippingAddress->state
primary_recipients->shipping_info->address->admin_area_2 => shippingAddress->city
primary_recipients->shipping_info->address->postal_code => shippingAddress->zip
primary_recipients->shipping_info->address->country_code => shippingAddress->country->id
primary_recipients->shipping_info->email_address => email
primary_recipients->shipping_info->phones->country_code=>"001" 
primary_recipients->shipping_info->phones->national_number => shippingAddress->addrPhone (strip "+1", "1" from beginning)
items[n]->name => item->items[n]->custcol26
items[n]->description => item->items[n]->description
items[n]->quantity =>item->items[n]->quantity
items[n]->unit_amount->currency_code =>currency->refname ("US Dollar=USD" | "Canadian Dollar =CAD")
items[n]->unit_amount->value =>item->items[n]->amount
