-- basi_di_dati.vista_dashboard_admin source

CREATE OR REPLACE
ALGORITHM = UNDEFINED VIEW `vista_dashboard_admin` AS
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

CREATE OR REPLACE
ALGORITHM = UNDEFINED VIEW `vista_dashboard_cliente` AS
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

CREATE OR REPLACE
ALGORITHM = UNDEFINED VIEW `vista_dashboard_medico` AS
select
    `u`.`id` AS `id_medico`,
    count((case when ((`v`.`data_visita` = curdate()) and (`v`.`stato` = 'prenotata')) then 1 end)) AS `visite_oggi`,
    count(distinct (case when (`v`.`stato` = 'completata') then `v`.`id_cliente` end)) AS `pazienti_unici`,
    coalesce(round(avg(`r`.`punteggio`), 1), 0) AS `rating_medio`
from
    ((`utente` `u`
left join `visita` `v` on
    ((`u`.`id` = `v`.`id_medico`)))
left join `recensione` `r` on
    ((`v`.`codice` = `r`.`codice_visita`)))
where
    (`u`.`ruolo` = 'medico')
group by
    `u`.`id`;