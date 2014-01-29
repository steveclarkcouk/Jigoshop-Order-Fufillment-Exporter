# Jigoshop Fufillment Order Exporter

A simple plugin that allows orders to be exported as a CSV emailed or FTP'd to a server for an external fufillment company. At present it is not very flexible 
and was built for a specific site.

## Installation

Download the zip file or clone this repositry to your wp-content/plugins folder and install via the Wordpress plugin area in the Dashboard.

## Usage

This plugin activates a sub menu under the Jigoshop Orders menu in the Wordpress Dashboard. Clicking this will give you access to the fufillment plugin which will display any orders awaiting exporting and some settings for email and FTP.

Clicking the 'Process CSV Fufilment' button will get all the orders and create a CSV which will either be emailed or FTP'd. 

You can unfufill any order  or view when it was fufilled by clicking into an Order within the Wordpress dashboard and looking for the Fufillment Export Status box. 