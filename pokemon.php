<?php
function fetchWithCache(string $url, string $cacheFile, int $ttl = 86400)
{
    // If cache exists and is fresh
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    $json = @file_get_contents($url);
    if ($json === false) return null;

    // Ensure cache folder exists
    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0777, true);
    }

    file_put_contents($cacheFile, $json);
    return json_decode($json, true);
}

if (!isset($_GET['id'])) {
    die("No Pokémon selected.");
}

$id = intval($_GET['id']);

// Basic Pokémon data
// $apiUrl = "https://pokeapi.co/api/v2/pokemon/$id/";
// $json = file_get_contents($apiUrl);
// $pokemon = json_decode($json, true);

$pokemon = fetchWithCache(
    "https://pokeapi.co/api/v2/pokemon/$id/",
    __DIR__ . "/cache/pokemon_$id.json"
);

if (!$pokemon) {
    die("Pokémon not found.");
}

$name = ucfirst($pokemon['name']);
$weight = $pokemon['weight'];
$height = $pokemon['height'];
$art = $pokemon['sprites']['other']['official-artwork']['front_default'] ?? '';

// -------------------------------------
// Fetch species data for description
// -------------------------------------

// $speciesUrl = $pokemon['species']['url'];
// $speciesJson = file_get_contents($speciesUrl);
// $species = json_decode($speciesJson, true);

$speciesId = basename(trim($pokemon['species']['url'], '/'));

$species = fetchWithCache(
    $pokemon['species']['url'],
    __DIR__ . "/cache/species_$speciesId.json"
);

$flavor = "No description available.";

foreach ($species['flavor_text_entries'] as $entry) {
    if ($entry['language']['name'] === 'en') {
        $flavor = $entry['flavor_text'];
        break;
    }
}

$encountersUrl = "https://pokeapi.co/api/v2/pokemon/$id/encounters";
// $encountersJson = file_get_contents($encountersUrl);
// $encounters = json_decode($encountersJson, true);

$encounters = fetchWithCache(
    "https://pokeapi.co/api/v2/pokemon/$id/encounters",
    __DIR__ . "/cache/encounters_$id.json"
);


// Get species data (already done)

// $evoChainUrl = $species['evolution_chain']['url'];
// $evoJson = file_get_contents($evoChainUrl);
// $evoData = json_decode($evoJson, true);

// $evoData = fetchWithCache(
//     $species['evolution_chain']['url'],
//     __DIR__ . "/cache/evolution_$id.json"
// );

$chainId = basename(trim($species['evolution_chain']['url'], '/'));

$evoData = fetchWithCache(
    $species['evolution_chain']['url'],
    __DIR__ . "/cache/evolution_chain_$chainId.json"
);

// Recursive function to flatten evolution chain

// function getEvolutionChain($chain, &$result = [])
// {
//     $speciesJson = file_get_contents($chain['species']['url']);
//     if (!$speciesJson) return $result;

//     $speciesData = json_decode($speciesJson, true);
//     if (!$speciesData || empty($speciesData['varieties'])) return $result;

//     $pokemonUrl = $speciesData['varieties'][0]['pokemon']['url'];
//     $pokeJson = file_get_contents($pokemonUrl);
//     if (!$pokeJson) return $result;

//     $pokeData = json_decode($pokeJson, true);
//     if (!$pokeData) return $result;

//     $result[] = [
//         'id' => $pokeData['id'],
//         'name' => $chain['species']['name'],
//         'image' =>
//         $pokeData['sprites']['other']['official-artwork']['front_default']
//             ?? $pokeData['sprites']['front_default']
//     ];

//     foreach ($chain['evolves_to'] as $evo) {
//         getEvolutionChain($evo, $result);
//     }

//     return $result;
// }

function getEvolutionChain($chain, &$result = [])
{
    $speciesId = basename(trim($chain['species']['url'], '/'));

    $speciesData = fetchWithCache(
        $chain['species']['url'],
        __DIR__ . "/cache/species_$speciesId.json"
    );

    if (!$speciesData || empty($speciesData['varieties'])) return $result;

    $pokemonUrl = $speciesData['varieties'][0]['pokemon']['url'];
    $pokemonId = basename(trim($pokemonUrl, '/'));

    $pokeData = fetchWithCache(
        $pokemonUrl,
        __DIR__ . "/cache/pokemon_$pokemonId.json"
    );

    if (!$pokeData) return $result;

    $result[] = [
        'id' => $pokeData['id'],
        'name' => $chain['species']['name'],
        'image' =>
        $pokeData['sprites']['other']['official-artwork']['front_default']
            ?? $pokeData['sprites']['front_default']
    ];

    foreach ($chain['evolves_to'] as $evo) {
        getEvolutionChain($evo, $result);
    }

    return $result;
}


// $evolutionChain = getEvolutionChain($evoData['chain']);

$evolutionChain = [];
if (!empty($evoData['chain'])) {
    $evolutionChain = getEvolutionChain($evoData['chain']);
}

$types = $pokemon['types'];
$primaryType = $pokemon['types'][0]['type']['name'] ?? null;
$secondaryType = $pokemon['types'][1]['type']['name'] ?? null;
$damageRelations = [];

// foreach ($types as $t) {
// $typeUrl = $t['type']['url'];
// $typeJson = file_get_contents($typeUrl);
// $typeData = json_decode($typeJson, true);
//     if ($typeJson === false) continue;

//     $damageRelations[$t['type']['name']] = $typeData['damage_relations'];
// }

foreach ($types as $t) {
    $typeName = $t['type']['name'];

    $typeData = fetchWithCache(
        $t['type']['url'],
        __DIR__ . "/cache/type_$typeName.json"
    );

    if ($typeData) {
        $damageRelations[$typeName] = $typeData['damage_relations'];
    }
}

$effectiveness = [];

foreach ($damageRelations as $type => $relations) {

    foreach ($relations['double_damage_from'] as $t) {
        $effectiveness[$t['name']] = ($effectiveness[$t['name']] ?? 1) * 2;
    }

    foreach ($relations['half_damage_from'] as $t) {
        $effectiveness[$t['name']] = ($effectiveness[$t['name']] ?? 1) * 0.5;
    }

    foreach ($relations['no_damage_from'] as $t) {
        $effectiveness[$t['name']] = 0;
    }
}

$groups = [
    '4× Weak' => [],
    '2× Weak' => [],
    '½× Resistant' => [],
    '¼× Resistant' => [],
    'Immune' => []
];

foreach ($effectiveness as $type => $value) {
    if ($value === 4) $groups['4× Weak'][] = $type;
    elseif ($value === 2) $groups['2× Weak'][] = $type;
    elseif ($value === 0.5) $groups['½× Resistant'][] = $type;
    elseif ($value === 0.25) $groups['¼× Resistant'][] = $type;
    elseif ($value === 0) $groups['Immune'][] = $type;
}

$prev = $id > 1 ? $id - 1 : null;
$next = ($id < 1025) ? $id + 1 : null;

$typeColors = [
    'normal' => '#a6a6a6ff',
    'fire' => '#ffb677ff',
    'water' => '#9bd2ffff',
    'grass' => '#c4ffa0ff',
    'electric' => '#fff281ff',
    'ice' => '#d3f8ffff',
    'fighting' => '#ff9696ff',
    'poison' => '#dc7affff',
    'ground' => '#dfd1a5ff',
    'flying' => '#cfcfffff',
    'psychic' => '#ffb3d9ff',
    'bug' => '#e3ffa3ff',
    'rock' => '#dec68dff',
    'ghost' => '#c9c0ebff',
    'dragon' => '#a7c7ffff',
    'dark' => '#414141ff',
    'steel' => '#bec6ccff',
    'fairy' => '#ffdff7ff',
];

// Build gradient if dual type
$bodyStyle = '';

if ($primaryType) {
    $color1 = $typeColors[$primaryType] ?? '#ffffff';
    $bodyStyle .= "--type1: $color1;";
}

if ($secondaryType) {
    $color2 = $typeColors[$secondaryType] ?? '#ffffff';
    $bodyStyle .= "--type2: $color2;";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($name) ?> Details</title>

    <?php
    $favicon = $pokemon['sprites']['other']['official-artwork']['front_default']
        ?? $pokemon['sprites']['front_default']
        ?? null;
    ?>

    <?php if ($favicon): ?>
        <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>">
    <?php endif; ?>


    <link rel="stylesheet" href="css/style.css">
</head>


<body
    style="<?= htmlspecialchars($bodyStyle) ?>"
    class="<?= $primaryType ? 'type-' . htmlspecialchars($primaryType) : '' ?><?= $secondaryType ? ' dual-type' : '' ?>">


    <a href="index.php">⬅ Back to Pokédex</a>

    <div class="nav-buttons">
        <?php if ($prev): ?>
            <a class="nav-btn prev-btn" href="pokemon.php?id=<?= $prev ?>">⬅ Previous</a>
        <?php endif; ?>

        <?php if ($next): ?>
            <a class="nav-btn next-btn" href="pokemon.php?id=<?= $next ?>">Next ➡</a>
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

        <h3>Cries</h3>
        <?php if (!empty($pokemon['cries']['latest'])): ?>
            <audio controls>
                <source src="<?= htmlspecialchars($pokemon['cries']['latest']) ?>" type="audio/ogg">
                Your browser does not support the audio tag.
            </audio>
        <?php else: ?>
            <p>No cry available.</p>
        <?php endif; ?>


        <h3>Types</h3>
        <div class="type-icons">
            <?php foreach ($pokemon['types'] as $type):
                $typeName = $type['type']['name'];
                $typeIcon = "images/" . $typeName . "1.png";
            ?>
                <img src="<?= htmlspecialchars($typeIcon) ?>"
                    alt="<?= htmlspecialchars($typeName) ?>"
                    class="type-icon">
            <?php endforeach; ?>
        </div>

        <h3>Type Effectiveness</h3>

        <?php foreach ($groups as $label => $types): ?>
            <?php if (!empty($types)): ?>
                <p><strong><?= $label ?>:</strong></p>
                <div class="type-icons">
                    <?php foreach ($types as $t):
                        $icon = "images/" . $t . "1.png";
                    ?>
                        <img src="<?= htmlspecialchars($icon) ?>" alt="<?= htmlspecialchars($t) ?>" class="type-icon">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <h3>Damage Relations</h3>
        <?php foreach ($damageRelations as $typeName => $relations): ?>
            <h4><?= htmlspecialchars(ucfirst($typeName)) ?></h4>
            <ul>
                <li>
                    <strong>Double Damage From:</strong>
                    <?php foreach ($relations['double_damage_from'] as $r):
                        $tName = $r['name'];
                        $icon = "images/" . $tName . "1.png";
                    ?>
                        <img src="<?= htmlspecialchars($icon) ?>" alt="<?= htmlspecialchars($tName) ?>" class="type-icon">
                    <?php endforeach; ?>
                </li>

                <li>
                    <strong>Half Damage From:</strong>
                    <?php foreach ($relations['half_damage_from'] as $r):
                        $tName = $r['name'];
                        $icon = "images/" . $tName . "1.png";
                    ?>
                        <img src="<?= htmlspecialchars($icon) ?>" alt="<?= htmlspecialchars($tName) ?>" class="type-icon">
                    <?php endforeach; ?>
                </li>

                <li>
                    <strong>No Damage From:</strong>
                    <?php foreach ($relations['no_damage_from'] as $r):
                        $tName = $r['name'];
                        $icon = "images/" . $tName . "1.png";
                    ?>
                        <img src="<?= htmlspecialchars($icon) ?>" alt="<?= htmlspecialchars($tName) ?>" class="type-icon">
                    <?php endforeach; ?>
                </li>
            </ul>
        <?php endforeach; ?>

        <h3>Abilities</h3>
        <ul>
            <?php foreach ($pokemon['abilities'] as $ab): ?>
                <li><?= htmlspecialchars(ucfirst($ab['ability']['name'])) ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>Base Stats</h3>

        <?php
        // Max reasonable stat is ~255
        $maxStat = 255;
        ?>

        <?php foreach ($pokemon['stats'] as $stat):
            $value = $stat['base_stat'];
            $percent = min(100, ($value / $maxStat) * 100);
        ?>
            <div class="stat-row">
                <div class="stat-label">
                    <?= htmlspecialchars(ucfirst($stat['stat']['name'])) ?>
                    <span class="stat-value"><?= $value ?></span>
                </div>
                <div class="stat-bar-bg">
                    <div class="stat-bar" style="width: <?= $percent ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>

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

        <div class="evo-chain">
            <?php foreach ($evolutionChain as $i => $evo): ?>
                <div class="evo-item">
                    <a href="pokemon.php?id=<?= $evo['id'] ?>">
                        <?php if (!empty($evo['image'])): ?>
                            <img src="<?= htmlspecialchars($evo['image']) ?>"
                                alt="<?= htmlspecialchars($evo['name']) ?>">
                        <?php endif; ?>
                        <span><?= htmlspecialchars(ucfirst($evo['name'])) ?></span>
                    </a>
                </div>

                <?php if ($i < count($evolutionChain) - 1): ?>
                    <div class="evo-arrow">➔</div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>



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


        <h3>Sprites</h3>
        <div class="sprite-row">
            <div class="sprite-col">
                <p><strong>Default:</strong></p>
                <?php if (!empty($pokemon['sprites']['front_default'])): ?>
                    <img src="<?= htmlspecialchars($pokemon['sprites']['front_default']) ?>" alt="Front Default">
                <?php endif; ?>

                <?php if (!empty($pokemon['sprites']['back_default'])): ?>
                    <img src="<?= htmlspecialchars($pokemon['sprites']['back_default']) ?>" alt="Back Default">
                <?php endif; ?>
            </div>

            <div class="sprite-col">
                <p><strong>Shiny:</strong></p>
                <?php if (!empty($pokemon['sprites']['front_shiny'])): ?>
                    <img src="<?= htmlspecialchars($pokemon['sprites']['front_shiny']) ?>" alt="Front Shiny">
                <?php endif; ?>

                <?php if (!empty($pokemon['sprites']['back_shiny'])): ?>
                    <img src="<?= htmlspecialchars($pokemon['sprites']['back_shiny']) ?>" alt="Back Shiny">
                <?php endif; ?>
            </div>
        </div>

        <h3>Animated Sprites (Gen 5)</h3>

        <?php
        $anim = $pokemon['sprites']['versions']['generation-v']['black-white']['animated'] ?? null;
        $hasAnim = $anim && (
            !empty($anim['front_default']) ||
            !empty($anim['back_default']) ||
            !empty($anim['front_shiny']) ||
            !empty($anim['back_shiny'])
        );
        ?>

        <?php if (!$hasAnim): ?>
            <p>No animated sprites available.</p>
        <?php else: ?>
            <div class="sprite-row">
                <div class="sprite-col">
                    <p><strong>Default:</strong></p>
                    <?php if (!empty($anim['front_default'])): ?>
                        <img src="<?= htmlspecialchars($anim['front_default']) ?>" alt="Animated Front">
                    <?php endif; ?>

                    <?php if (!empty($anim['back_default'])): ?>
                        <img src="<?= htmlspecialchars($anim['back_default']) ?>" alt="Animated Back">
                    <?php endif; ?>
                </div>

                <div class="sprite-col">
                    <p><strong>Shiny:</strong></p>
                    <?php if (!empty($anim['front_shiny'])): ?>
                        <img src="<?= htmlspecialchars($anim['front_shiny']) ?>" alt="Animated Shiny Front">
                    <?php endif; ?>

                    <?php if (!empty($anim['back_shiny'])): ?>
                        <img src="<?= htmlspecialchars($anim['back_shiny']) ?>" alt="Animated Shiny Back">
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <h3>Gender Differences</h3>

        <?php
        $hasFemale =
            !empty($pokemon['sprites']['front_female']) ||
            !empty($pokemon['sprites']['back_female']) ||
            !empty($pokemon['sprites']['front_shiny_female']) ||
            !empty($pokemon['sprites']['back_shiny_female']);
        ?>

        <?php if (!$hasFemale): ?>
            <p>No gender differences.</p>
        <?php else: ?>
            <div class="sprite-row">
                <div class="sprite-col">
                    <p><strong>Female (Normal):</strong></p>
                    <?php if (!empty($pokemon['sprites']['front_female'])): ?>
                        <img src="<?= htmlspecialchars($pokemon['sprites']['front_female']) ?>" alt="Female Front">
                    <?php endif; ?>

                    <?php if (!empty($pokemon['sprites']['back_female'])): ?>
                        <img src="<?= htmlspecialchars($pokemon['sprites']['back_female']) ?>" alt="Female Back">
                    <?php endif; ?>
                </div>

                <div class="sprite-col">
                    <p><strong>Female (Shiny):</strong></p>
                    <?php if (!empty($pokemon['sprites']['front_shiny_female'])): ?>
                        <img src="<?= htmlspecialchars($pokemon['sprites']['front_shiny_female']) ?>" alt="Female Shiny Front">
                    <?php endif; ?>

                    <?php if (!empty($pokemon['sprites']['back_shiny_female'])): ?>
                        <img src="<?= htmlspecialchars($pokemon['sprites']['back_shiny_female']) ?>" alt="Female Shiny Back">
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <h3>Alternate Forms</h3>

        <?php if (count($species['varieties']) <= 1): ?>
            <p>No alternate forms.</p>
        <?php else: ?>
            <div class="evo-chain">

                <?php foreach ($species['varieties'] as $form):

                    $pokemonUrl = $form['pokemon']['url'];
                    $pokemonId = basename(trim($pokemonUrl, '/'));

                    $formData = fetchWithCache(
                        $pokemonUrl,
                        __DIR__ . "/cache/pokemon_$pokemonId.json"
                    );

                    if (!$formData) continue;

                    $baseName = ucfirst($species['name']);
                    $slug = $formData['name'];

                    $formName = str_replace($species['name'] . '-', '', $slug);
                    $formName = ucfirst(str_replace('-', ' ', $formName));

                    $displayName = ($slug !== $species['name'])
                        ? "$baseName ($formName)"
                        : $baseName;

                    $formImg =
                        $formData['sprites']['other']['official-artwork']['front_default']
                        ?? $formData['sprites']['front_default']
                        ?? null;
                ?>
                    <div class="evo-item">
                        <a href="pokemon.php?id=<?= $formData['id'] ?>">
                            <?php if ($formImg): ?>
                                <img src="<?= htmlspecialchars($formImg) ?>">
                            <?php endif; ?>
                            <span><?= htmlspecialchars($displayName) ?></span>
                        </a>
                    </div>

                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <details>
            <summary>
                <h3>Encounter Locations</h3>
            </summary>

            <?php if (empty($encounters)): ?>
                <p>No encounter data available.</p>
            <?php else: ?>
                <ul class="encounter-list">
                    <?php foreach ($encounters as $enc):
                        $loc = ucfirst(str_replace('-', ' ', $enc['location_area']['name']));
                    ?>
                        <li>
                            <strong><?= htmlspecialchars($loc) ?></strong>
                            <ul>
                                <?php foreach ($enc['version_details'] as $version):
                                    $game = ucfirst($version['version']['name']);
                                ?>
                                    <li><?= htmlspecialchars($game) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </details>
    </div>
</body>

</html>