# Titlo DB outage page (static). No nginx reload needed after first install:
# creating/removing storage/app/outage/ENABLED toggles the page.
# Installed into: /etc/nginx/fastpanel2-sites/cabinet_titl_usr/cabinet.titlo.ru.includes
# between markers BEGIN/END CABINET-TITLO-DB-OUTAGE

set $cabinet_titlo_outage "";
if (-f /var/www/cabinet_titl_usr/data/www/cabinet.titlo.ru/storage/app/outage/ENABLED) {
    set $cabinet_titlo_outage "1";
}
# Internal redirect на /outage.html не должен снова отдавать голый 503
if ($uri = /outage.html) {
    set $cabinet_titlo_outage "0";
}
if ($cabinet_titlo_outage = "1") {
    return 503;
}
error_page 503 /outage.html;
location = /outage.html {
    root /var/www/cabinet_titl_usr/data/www/cabinet.titlo.ru/public;
    internal;
    add_header Cache-Control "no-store" always;
    add_header Retry-After 300 always;
    default_type text/html;
    charset utf-8;
}
