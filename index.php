<?php

$cacheFile = __DIR__ . '/pokemon_cache.json';
$cacheLifetime = 60 * 60 * 24;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
    $list = json_decode(file_get_contents($cacheFile), true);
} else {

    $listUrl = "https://pokeapi.co/api/v2/pokemon?limit=1025";
    $listJson = file_get_contents($listUrl);

    file_put_contents($cacheFile, $listJson);

    $list = json_decode($listJson, true);
}

// $listUrl = "https://pokeapi.co/api/v2/pokemon?limit=1025";
// $listJson = file_get_contents($listUrl);
// $list = json_decode($listJson, true);

?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Pokémon List</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <h1>Domi's Pokedex</h1>

    <input type="text" id="search" placeholder="Search by name or ID...">

    <select id="typeFilter">
        <option value="">All Types</option>
        <option value="normal">Normal</option>
        <option value="fire">Fire</option>
        <option value="water">Water</option>
        <option value="grass">Grass</option>
        <option value="electric">Electric</option>
        <option value="ice">Ice</option>
        <option value="fighting">Fighting</option>
        <option value="poison">Poison</option>
        <option value="ground">Ground</option>
        <option value="flying">Flying</option>
        <option value="psychic">Psychic</option>
        <option value="bug">Bug</option>
        <option value="rock">Rock</option>
        <option value="ghost">Ghost</option>
        <option value="dragon">Dragon</option>
        <option value="dark">Dark</option>
        <option value="steel">Steel</option>
        <option value="fairy">Fairy</option>
    </select>

    <hr>

    <div class="pokemon-grid">
        <?php foreach ($list['results'] as $pokemon):
            preg_match('/pokemon\/(\d+)/', $pokemon['url'], $matches);
            $id = $matches[1];

            $artworkUrl = "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/$id.png";
        ?>
            <div class="poke-item"
                data-name="<?= strtolower($pokemon['name']) ?>"
                data-id="<?= $id ?>"
                data-types="">

                <a href="pokemon.php?id=<?= $id ?>">
                    <h2><?= htmlspecialchars(ucfirst($pokemon['name'])) ?> (#<?= $id ?>)</h2>
                    <img src="<?= htmlspecialchars($artworkUrl) ?>">
                </a>
            </div>
        <?php endforeach; ?>
    </div>


</body>

<script>
    const items = document.querySelectorAll(".poke-item");

    // Load types for each Pokémon (quick & async)
    items.forEach(item => {
        const id = item.dataset.id;
        fetch(`https://pokeapi.co/api/v2/pokemon/${id}`)
            .then(res => res.json())
            .then(data => {
                const types = data.types.map(t => t.type.name);
                item.dataset.types = types.join(",");
            });
    });

    const searchInput = document.getElementById("search");
    const typeFilter = document.getElementById("typeFilter");

    // Filtering function
    function applyFilters() {
        const search = searchInput.value.toLowerCase();
        const type = typeFilter.value;

        items.forEach(item => {
            const name = item.dataset.name;
            const id = item.dataset.id;
            const types = item.dataset.types.split(",");

            let visible = true;

            // Search condition
            if (search && !(name.includes(search) || id === search)) {
                visible = false;
            }

            // Type filter condition
            if (type && !types.includes(type)) {
                visible = false;
            }

            item.style.display = visible ? "block" : "none";
        });
    }

    searchInput.addEventListener("input", applyFilters);
    typeFilter.addEventListener("change", applyFilters);

    // Change body background when selecting a type
    typeFilter.addEventListener("change", () => {
        const type = typeFilter.value;

        // Remove any previous type class
        document.body.className = document.body.className
            .split(" ")
            .filter(c => !c.startsWith("type-"))
            .join(" ");

        // Apply new type class
        if (type) {
            document.body.classList.add("type-" + type);
        }
    });
</script>

</html>