<?php
namespace Utils;

require_once __DIR__ . '/../../vendor/autoload.php';

class MongoLogger
{
    private $collection;

    public function __construct()
    {
        $client = new \MongoDB\Client("mongodb://127.0.0.1:27017");
        $this->collection = $client->centro_medico->audit_logs;
    }

    /**
     * @param int $idUtente ID dell'utente che compie l'azione
     * @param string $ruolo (admin, medico, cliente)
     * @param string $azione (es. INSERT, UPDATE, DELETE, CANCEL)
     * @param string $entita La tabella o macro-area (es. Visita, Utente, Centro)
     * @param mixed $idEntita L'ID del record modificato
     * @param array $dettagli Dati extra (es. vecchi/nuovi valori)
     */
    public function logAzione($idUtente, $ruolo, $azione, $entita, $idEntita = null, $dettagli = [])
    {
        $document = [
            'timestamp' => new \MongoDB\BSON\UTCDateTime(),
            'id_utente' => $idUtente,
            'ruolo' => $ruolo,
            'azione' => $azione,
            'entita' => $entita,
            'id_entita' => $idEntita,
            'dettagli' => $dettagli,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ];

        try {
            $this->collection->insertOne($document);
        } catch (\Exception $e) {
        }
    }
}