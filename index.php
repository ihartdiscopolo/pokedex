<?php

$cacheFile = __DIR__ . "/cache/pokemon_list.json";
// $cacheTime = 5;
$cacheTime = 24 * 60 * 60; // 24 hours

// If cache exists and is fresh, load it
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    $listJson = file_get_contents($cacheFile);
} else {
    // Fetch fresh data
    $listUrl = "https://pokeapi.co/api/v2/pokemon?limit=1025";
    $listJson = file_get_contents($listUrl);

    // Ensure /cache/ folder exists
    if (!is_dir(__DIR__ . "/cache")) {
        mkdir(__DIR__ . "/cache", 0777, true);
    }

    // Save to cache
    file_put_contents($cacheFile, $listJson);
}

$list = json_decode($listJson, true);

?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Pokémon List</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/x-icon" href="images/Poké_Ball_icon.svg.png">
</head>

<body>
    <!-- HAMBURGER BUTTON -->
    <div id="menuBtn">☰</div>

    <!-- TEAM BUILDER SIDEBAR -->
    <div id="teamSidebar">
        <h2>Your Team</h2>
        <p>Select Pokémon to add them to your team.</p>
        <button id="randomTeamBtn" class="random-btn">Randomize Team!</button>


        <div id="teamSlots" class="team-slots"></div>

        <h3>Team Type Coverage</h3>
        <div id="teamCoverage"></div>
    </div>

    <div id="contentWrapper">

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

        <label class="fav-toggle">
            <input type="checkbox" id="favOnly">
            Favorites
        </label>

        <button id="randomPokemonBtn" class="random-small-btn">
            Random Pokemon
        </button>

        <hr>

        <div class="pokemon-grid">
            <?php foreach ($list['results'] as $pokemon):
                preg_match('/pokemon\/(\d+)\/$/', $pokemon['url'], $matches);
                $id = $matches[1] ?? null;
                $artworkUrl = "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/$id.png";
            ?>
                <div class="poke-item"
                    data-name="<?= strtolower($pokemon['name']) ?>"
                    data-id="<?= $id ?>"
                    data-types="">

                    <!-- Favorite checkbox + star -->
                    <label class="fav-label">
                        <input type="checkbox" class="fav-checkbox" data-id="<?= $id ?>">
                        <span class="star-emoji"></span>
                    </label>

                    <a href="pokemon.php?id=<?= $id ?>">
                        <h2 class="poke-name"><?= htmlspecialchars(ucfirst($pokemon['name'])) ?></h2>
                        <p class="poke-id">#<?= $id ?></p>
                        <img src="<?= htmlspecialchars($artworkUrl) ?>" alt="<?= htmlspecialchars($pokemon['name']) ?>">
                    </a>

                    <button class="add-team-btn" data-id="<?= $id ?>" data-name="<?= $pokemon['name'] ?>">Add to Team</button>

                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>

<script>
    const items = document.querySelectorAll(".poke-item");

    // Track how many type requests are left
    let pendingTypes = items.length;

    // Load types for each Pokémon (quick & async)
    items.forEach(item => {
        const id = item.dataset.id;

        fetch(`https://pokeapi.co/api/v2/pokemon/${id}`)
            .then(res => res.json())
            .then(data => {
                const types = data.types.map(t => t.type.name);
                item.dataset.types = types.join(",");
            })
            .catch(() => {
                // fallback if API fails
                item.dataset.types = "";
            })
            .finally(() => {
                pendingTypes--;

                // When ALL types are loaded → apply filters once correctly
                if (pendingTypes === 0) {
                    applyFilters();
                }
            });
    });

    const searchInput = document.getElementById("search");
    const typeFilter = document.getElementById("typeFilter");
    const favOnlyCheckbox = document.getElementById("favOnly");

    // Load favorites from localStorage
    let favorites = JSON.parse(localStorage.getItem("favorites") || "[]");

    // Pre-check favorite boxes on page load
    document.querySelectorAll(".fav-checkbox").forEach(cb => {
        const id = cb.dataset.id;
        if (favorites.includes(id)) cb.checked = true;

        cb.addEventListener("change", () => {
            if (cb.checked) favorites.push(id);
            else favorites = favorites.filter(f => f !== id);

            localStorage.setItem("favorites", JSON.stringify(favorites));
            applyFilters();
        });
    });

    // Filtering function
    function applyFilters() {
        const search = searchInput.value.toLowerCase();
        const type = typeFilter.value;
        const favOnly = favOnlyCheckbox.checked;

        items.forEach(item => {
            const name = item.dataset.name;
            const id = item.dataset.id;
            const types = item.dataset.types ?
                item.dataset.types.split(",").filter(t => t.length > 0) : [];

            let visible = true;

            if (search && !(name.includes(search) || id === search)) visible = false;
            if (type && !types.includes(type)) visible = false;
            if (favOnly && !favorites.includes(id)) visible = false;

            item.style.display = visible ? "block" : "none";
        });
    }

    // Listeners
    searchInput.addEventListener("input", applyFilters);
    typeFilter.addEventListener("change", applyFilters);
    favOnlyCheckbox.addEventListener("change", applyFilters);

    // Background color logic
    typeFilter.addEventListener("change", () => {
        const type = typeFilter.value;
        document.body.className = [...document.body.classList]
            .filter(c => !c.startsWith("type-"))
            .join(" ");
        if (type) document.body.classList.add("type-" + type);
    });

    applyFilters();

    const menuBtn = document.getElementById("menuBtn");
    const teamSidebar = document.getElementById("teamSidebar");

    menuBtn.addEventListener("click", () => {
        teamSidebar.classList.toggle("open");
    });

    let team = JSON.parse(localStorage.getItem("team") || "[]");

    function saveTeam() {
        localStorage.setItem("team", JSON.stringify(team));
        renderTeam();
    }

    document.querySelectorAll(".add-team-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            const id = btn.dataset.id;
            const name = btn.dataset.name;

            if (team.length >= 6) {
                alert("Your Pokémon team is full (max 6).");
                return;
            }

            if (!team.find(p => p.id === id)) {
                team.push({
                    id,
                    name
                });
                saveTeam();
            }
        });
    });

    function renderTeam() {
        const slotBox = document.getElementById("teamSlots");
        slotBox.innerHTML = "";

        team.forEach((p, i) => {
            slotBox.innerHTML += `
            <div class="team-member">
                <img src="https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${p.id}.png">
                <strong>${p.name}</strong>
                <button onclick="removeFromTeam(${i})">❌</button>
            </div>
        `;
        });

        updateTeamCoverage();
    }

    function removeFromTeam(index) {
        team.splice(index, 1);
        saveTeam();
    }

    /* ---------------------------
    TEAM TYPE COVERAGE
    --------------------------- */
    async function updateTeamCoverage() {
        const coverageBox = document.getElementById("teamCoverage");
        let combinedWeak = {};
        let combinedStrong = {};

        for (let p of team) {
            let data = await fetch(`https://pokeapi.co/api/v2/pokemon/${p.id}`).then(r => r.json());
            let types = data.types.map(t => t.type.name);

            for (let t of types) {
                let tData = await fetch(`https://pokeapi.co/api/v2/type/${t}`).then(r => r.json());

                tData.damage_relations.double_damage_from.forEach(w => {
                    combinedWeak[w.name] = (combinedWeak[w.name] || 0) + 1;
                });

                tData.damage_relations.half_damage_from.forEach(s => {
                    combinedStrong[s.name] = (combinedStrong[s.name] || 0) + 1;
                });
            }
        }

        let html = "<h4>Weak To:</h4><div class='coverage-row'>";
        for (let t in combinedWeak) {
            html += `<span class="type-chip type-${t}">${t} x${combinedWeak[t]}</span>`;
        }
        html += "</div>";

        html += "<h4>Resistant To:</h4><div class='coverage-row'>";
        for (let t in combinedStrong) {
            html += `<span class="type-chip type-${t}">${t} x${combinedStrong[t]}</span>`;
        }
        html += "</div>";

        coverageBox.innerHTML = html;
    }

    /* ----------------------------------------------
       FIXED RANDOM TEAM GENERATOR (WORKING VERSION)
    ---------------------------------------------- */

    function getAllPokemonPool(fromFavorites = false) {
        const nodes = Array.from(document.querySelectorAll(".poke-item"));
        const pool = nodes
            .map(item => {
                const id = item.dataset.id;
                let name = item.dataset.name || "";
                name = name.charAt(0).toUpperCase() + name.slice(1);
                return {
                    id,
                    name
                };
            })
            .filter(p => p.id);

        if (fromFavorites) {
            return pool.filter(p => favorites.includes(p.id));
        }

        return pool;
    }

    function pickRandomN(arr, n) {
        const copy = arr.slice();
        for (let i = copy.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [copy[i], copy[j]] = [copy[j], copy[i]];
        }
        return copy.slice(0, n);
    }

    document.getElementById("randomTeamBtn").addEventListener("click", () => {
        const pool = getAllPokemonPool(false);

        const teamSize = Math.min(6, pool.length);
        const randomTeam = pickRandomN(pool, teamSize);

        team = randomTeam;
        saveTeam();
    });

    renderTeam();

    document.getElementById("randomPokemonBtn").addEventListener("click", () => {
        const items = Array.from(document.querySelectorAll(".poke-item"));
        if (!items.length) return;

        const randomItem = items[Math.floor(Math.random() * items.length)];
        const id = randomItem.dataset.id;

        window.location.href = `pokemon.php?id=${id}`;
    });
</script>

</html>