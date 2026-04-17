# Benchmarks

Die Benchmark-Skripte in diesem Verzeichnis sind als praktische
Entscheidungshilfe gedacht, nicht als Marketingmaterial.

## Was gemessen wird

Die Hauptsuite `write-performance.php` misst End-to-End-Schreibvorgaenge fuer:

- `kalle/xml` ueber das Baum-/Dokumentmodell
- `StreamingXmlWriter`
- `DOMDocument`, falls `ext-dom` verfuegbar ist
- `XMLWriter`, falls `ext-xmlwriter` verfuegbar ist

Abgedeckte Szenarien:

- kleines Dokument
- mittleres Dokument
- grosses Dokument
- namespace-lastiges Dokument

Pro Implementierung werden zwei Werte erfasst:

- Laufzeit: Gesamtzeit und Durchschnitt pro Iteration
- Speicher: maximales Peak-Delta pro Iteration relativ zur Startbelegung

Zusatzlich gibt es mit `document-vs-streaming.php` weiterhin einen kleineren
Fokus-Benchmark fuer die internen `kalle/xml`-Write-Pfade.

## Ausfuehrung

Von der Repository-Wurzel aus:

```bash
php benchmarks/write-performance.php
php benchmarks/write-performance.php medium
php benchmarks/write-performance.php namespace-heavy 25
php benchmarks/write-performance.php 50
```

Argumente fuer `write-performance.php`:

- erstes Argument optional: Szenario (`small`, `medium`, `large`, `namespace-heavy`, `all`)
- zweites Argument optional: Iterationen fuer alle ausgewaehlten Szenarien
- wenn nur eine Zahl uebergeben wird, gilt sie als Iterations-Override fuer alle Szenarien

Fokussierter Kalle-only-Vergleich:

```bash
php benchmarks/document-vs-streaming.php
php benchmarks/document-vs-streaming.php 5000 15
```

## Interpretation

Die Suite misst bewusst den praktischen Schreibpfad pro API, nicht eine
isolierte Mikro-Operation im Vakuum. Das bedeutet:

- das `kalle/xml`-Baummodell umfasst Dokumentaufbau plus Ausgabe
- `StreamingXmlWriter` und `XMLWriter` schreiben inkrementell
- `DOMDocument` umfasst DOM-Aufbau plus `saveXML()`

Die Ergebnisse zeigen deshalb Nutzungsprofile und grobe Trends, keine absolute
"Gewinnerbibliothek".

Vor dem Timing wird jede Implementierung einmal semantisch gegen die Baseline
geprueft. Damit benchmarken wir nicht versehentlich unterschiedliche XML-Daten.

## Grenzen des Setups

- CLI-Mikrobenchmarks sind empfindlich gegen CPU-Last, Turbo-Boost und Speicherzustand
- es gibt keine Prozess-Isolation oder statistische Auswertung ueber mehrere separate Runs
- Peak-Speicher ist nur eine praktische Naeherung, keine vollstaendige Heap-Analyse
- DOM- und XMLWriter-Ergebnisse haengen von den installierten PHP-Extensions und der PHP-Version ab
- reale Anwendungen koennen andere Hotspots haben, etwa I/O, Datenbeschaffung oder Objektaufbau ausserhalb des Writers

Keine generierten Reports oder einmaligen Profiling-Artefakte committen.
