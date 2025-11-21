<?php
if (!isset($_GET['id'])) {
    die("No Pokémon selected.");
}

$id = intval($_GET['id']);

// Basic Pokémon data
$apiUrl = "https://pokeapi.co/api/v2/pokemon/$id/";
$json = file_get_contents($apiUrl);

if (!$json) {
    die("Pokémon not found.");
}

$pokemon = json_decode($json, true);

$name = ucfirst($pokemon['name']);
$weight = $pokemon['weight'];
$height = $pokemon['height'];
$art = $pokemon['sprites']['other']['official-artwork']['front_default'] ?? '';

// -------------------------------------
// Fetch species data for description
// -------------------------------------
$speciesUrl = $pokemon['species']['url'];
$speciesJson = file_get_contents($speciesUrl);
$species = json_decode($speciesJson, true);

$flavor = "No description available.";

foreach ($species['flavor_text_entries'] as $entry) {
    if ($entry['language']['name'] === 'en') {
        $flavor = $entry['flavor_text'];
        break;
    }
}

// Get species data (already done)
$evoChainUrl = $species['evolution_chain']['url'];
$evoJson = file_get_contents($evoChainUrl);
$evoData = json_decode($evoJson, true);

// Recursive function to flatten evolution chain
function getEvolutionChain($chain)
{
    $evolutions = [];
    $evolutions[] = $chain['species']['name'];
    if (!empty($chain['evolves_to'])) {
        foreach ($chain['evolves_to'] as $e) {
            $evolutions = array_merge($evolutions, getEvolutionChain($e));
        }
    }
    return $evolutions;
}

$evolutionChain = getEvolutionChain($evoData['chain']);

$types = $pokemon['types'];
$damageRelations = [];

foreach ($types as $t) {
    $typeUrl = $t['type']['url'];
    $typeJson = file_get_contents($typeUrl);
    $typeData = json_decode($typeJson, true);

    $damageRelations[$t['type']['name']] = $typeData['damage_relations'];
}

$prev = $id > 1 ? $id - 1 : null;
$next = $id + 1; // allow next >151 if you want full dex; adjust if you want a cap
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($name) ?> Details</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <a href="index.php">⬅ Back to Pokédex</a>

    <div class="nav-buttons" style="margin-top:12px;">
        <?php if ($prev): ?>
            <a href="pokemon.php?id=<?= $prev ?>">⬅ Previous</a>
        <?php endif; ?>

        <?php if ($next): ?>
            <a href="pokemon.php?id=<?= $next ?>">Next ➡</a>
        <?php endif; ?>
    </div>

    <div class="pokemon-card">
        <div class="pokemon-header">
            <h1><?= htmlspecialchars($name) ?> (#<?= $id ?>)</h1>

            <?php if ($art): ?>
                <img src="<?= htmlspecialchars($art) ?>" alt="<?= htmlspecialchars($name) ?>">
            <?php else: ?>
                <div style="width:280px;height:280px;display:inline-block;background:#f0f0f0;border-radius:12px;line-height:280px;color:#999;">
                    No image
                </div>
            <?php endif; ?>
        </div>

        <p><strong>Height:</strong> <?= htmlspecialchars($height) ?></p>
        <p><strong>Weight:</strong> <?= htmlspecialchars($weight) ?></p>
        <p><strong>Base Experience:</strong> <?= htmlspecialchars($pokemon['base_experience']) ?></p>

        <h3>Description</h3>
        <p><?= nl2br(htmlspecialchars($flavor)) ?></p>

        <h3>Types</h3>
        <ul>
            <?php foreach ($pokemon['types'] as $type): ?>
                <li><?= htmlspecialchars(ucfirst($type['type']['name'])) ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>Damage Relations</h3>
        <?php foreach ($damageRelations as $typeName => $relations): ?>
            <h4><?= htmlspecialchars(ucfirst($typeName)) ?></h4>
            <ul>
                <li>Double Damage From: <?= implode(", ", array_map(fn($r) => ucfirst($r), array_column($relations['double_damage_from'], 'name'))) ?></li>
                <li>Half Damage From: <?= implode(", ", array_map(fn($r) => ucfirst($r), array_column($relations['half_damage_from'], 'name'))) ?></li>
                <li>No Damage From: <?= implode(", ", array_map(fn($r) => ucfirst($r), array_column($relations['no_damage_from'], 'name'))) ?></li>
            </ul>
        <?php endforeach; ?>

        <h3>Abilities</h3>
        <ul>
            <?php foreach ($pokemon['abilities'] as $ab): ?>
                <li><?= htmlspecialchars(ucfirst($ab['ability']['name'])) ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>Stats</h3>
        <ul>
            <?php foreach ($pokemon['stats'] as $stat): ?>
                <li><?= htmlspecialchars(ucfirst($stat['stat']['name'])) ?>: <?= htmlspecialchars($stat['base_stat']) ?></li>
            <?php endforeach; ?>
        </ul>

        <details>
            <summary>
                <h3>Moves</h3>
            </summary>
            <ul>
                <?php foreach ($pokemon['moves'] as $move): ?>
                    <li><?= htmlspecialchars(ucfirst($move['move']['name'])) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>

        <details>
            <summary>
                <h3>Game Appearances</h3>
            </summary>
            <ul>
                <?php foreach ($pokemon['game_indices'] as $game): ?>
                    <li><?= htmlspecialchars(ucfirst($game['version']['name'])) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>

        <h3>Evolution Chain</h3>
        <p class="evo-chain"><?= implode(" ➔ ", array_map(fn($n) => htmlspecialchars(ucfirst($n)), $evolutionChain)) ?></p>

        <h3>Held Items</h3>
        <ul>
            <?php if (empty($pokemon['held_items'])): ?>
                <li>None</li>
            <?php else: ?>
                <?php foreach ($pokemon['held_items'] as $item): ?>
                    <li><?= htmlspecialchars(ucfirst($item['item']['name'])) ?></li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

</body>

</html>