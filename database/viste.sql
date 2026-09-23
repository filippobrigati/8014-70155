-- basi_di_dati.vista_dashboard_admin source

CREATE OR REPLACE VIEW `vista_dashboard_admin` AS
select
    `ac`.`id_admin` AS `id_admin`,
    ifnull(sum(`cm`.`totale_medici`), 0) AS `totale_medici`,
    ifnull(sum(`cm`.`totale_incasso`), 0) AS `fatturato_totale`,
    (
    select
        count(`v`.`codice`)
    from
        ((`visita` `v`
    join `specializzazione` `s` on
        ((`v`.`id_specializzazione` = `s`.`id`)))
    join `admin_centro` `ac2` on
        ((`s`.`partita_iva_centro` = `ac2`.`partita_iva_centro`)))
    where
        ((`ac2`.`id_admin` = `ac`.`id_admin`)
            and (month(`v`.`data_visita`) = month(curdate()))
                and (year(`v`.`data_visita`) = year(curdate())))) AS `visite_mese`
from
    (`admin_centro` `ac`
join `centro_medico` `cm` on
    ((`ac`.`partita_iva_centro` = `cm`.`partita_iva`)))
group by
    `ac`.`id_admin`;


-- basi_di_dati.vista_dashboard_cliente source

CREATE OR REPLACE VIEW `vista_dashboard_cliente` AS
select
    `u`.`id` AS `id_cliente`,
    count((case when (`v`.`stato` = 'prenotata') then 1 end)) AS `visite_attive`,
    count((case when (`v`.`stato` = 'completata') then 1 end)) AS `visite_completate`
from
    (`utente` `u`
left join `visita` `v` on
    ((`u`.`id` = `v`.`id_cliente`)))
where
    (`u`.`ruolo` = 'cliente')
group by
    `u`.`id`;


-- basi_di_dati.vista_dashboard_medico source

CREATE OR REPLACE UNDEFINED VIEW `vista_dashboard_medico` AS
SELECT
    `u`.`id` AS `id_medico`,
    COUNT(CASE WHEN (`v`.`data_visita` = CURDATE() AND `v`.`stato` = 'prenotata') THEN 1 END) AS `visite_oggi`,
    COUNT(DISTINCT CASE WHEN (`v`.`stato` = 'completata') THEN `v`.`id_cliente` END) AS `pazienti_unici`,
    COALESCE(`sm`.`rating_medio`, 0.00) AS `rating_medio`
FROM `utente` `u`
LEFT JOIN `visita` `v` ON `u`.`id` = `v`.`id_medico`
LEFT JOIN `Statistiche_Medico` `sm` ON `u`.`id` = `sm`.`id_medico`
WHERE `u`.`ruolo` = 'medico'
GROUP BY 
    `u`.`id`, 
    `sm`.`rating_medio`;