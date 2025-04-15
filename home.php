<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <style>
    .pagination .page-item { margin: 0 5px; }
    .pagination-wrapper { display: flex; justify-content: center; align-items: center; }
    .modal { display: block; background: rgba(0,0,0,0.5);  }
    .modal-dialog {
        max-height: 80vh; 
        overflow: hidden; 
      }

    .modal-dialog-scrollable .modal-content {
      max-height: 70vh;
      overflow-y: auto; 
    }

    .modal {
      position: fixed; 
      top: 50%; 
      left: 50%; 
      transform: translate(-50%, -50%); 
    }
  </style>
</head>
<body>
  <div id="app" class="container mt-5">
    <?php if ($role === 'admin'): ?>
      <a href="add_user.php">Add New User</a><br><br>
    <?php endif; ?>

    <h2>Select Data Source</h2>
    <form @submit.prevent>
      <label for="source">Select Source:</label>
      <select v-model="selectedSource" id="source" @change="fetchTables" required>
        <optgroup label="MySQL Databases">
          <option v-for="db in mysqlDatabases" :key="db" :value="'mysql_' + db">{{ db }}</option>
        </optgroup>
        <optgroup label="MongoDB Databases">
          <option v-for="db in mongoDatabases" :key="db" :value="'mongodb_' + db">{{ db }}</option>
        </optgroup>
      </select>
      <br /><br />
    </form>

    <label for="table">Tables/Collections:</label>
    <select v-model="selectedTable" id="table" @change="showButtons = true" required>
      <option value="">Please select a source first</option>
      <option v-for="table in tables" :key="table" :value="table">{{ table }}</option>
    </select>

    <div v-if="showButtons" style="margin-top: 15px;">
      <button class="btn btn-primary" @click="loadRecords(1)">Browse</button>
      <!-- When Aggregate is clicked, open the Aggregate modal -->
      <button class="btn btn-secondary ml-2" @click="showAggregateModal = true">Aggregate</button>
    </div>

    <div id="recordOutput" style="margin-top: 20px;">
      <div v-if="records.length === 0 && recordsFetched">No records found.</div>
      <table v-else border="1" cellpadding="5" style="border-collapse: collapse;">
        <thead>
          <tr>
            <th v-for="key in tableHeaders" :key="key">{{ key }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(record, index) in records" :key="index">
            <td v-for="key in tableHeaders" :key="key">{{ record[key] }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div id="pagination" style="margin-top: 10px;" v-if="records.length > 0">
      <button class="btn btn-outline-primary" @click="loadRecords(currentPage - 1)" :disabled="currentPage === 1">Previous</button>
      <span id="pageIndicator" class="mx-2">Page {{ currentPage }} of {{ totalPages }}</span>
      <button class="btn btn-outline-primary" @click="loadRecords(currentPage + 1)" :disabled="currentPage === totalPages">Next</button>
    </div>
    <br />
    <a href="logout.php">Logout</a>

    <!-- Aggregate Modal -->
    <div class="modal" v-if="showAggregateModal">
      <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content p-4">
          <h4>Aggregate Query Builder</h4>
          <!-- Match Conditions -->
          <div>
            <h5>Match Conditions</h5>
            <div v-for="(cond, index) in aggregate.matchConditions" :key="index" class="form-row align-items-center mb-2">
              <div class="col">
                <input type="text" class="form-control" v-model="cond.field" placeholder="Field">
              </div>
              <div class="col">
                <input type="text" class="form-control" v-model="cond.value" placeholder="Value">
              </div>
              <div class="col-auto">
                <button class="btn btn-outline-danger" type="button" @click="removeMatchCondition(index)">
                  <i class="fas fa-minus"></i>
                </button>
              </div>
            </div>
            <button class="btn btn-outline-primary mb-3" type="button" @click="addMatchCondition">
              <i class="fas fa-plus"></i> Add Condition
            </button>
          </div>
          <!-- Select Fields -->
          <div>
            <h5>Select Fields</h5>
            <div v-for="(field, index) in aggregate.selectFields" :key="index" class="form-row align-items-center mb-2">
              <div class="col">
                <input type="text" class="form-control" v-model="aggregate.selectFields[index]" placeholder="Field">
              </div>
              <div class="col-auto">
                <button class="btn btn-outline-danger" type="button" @click="removeSelectField(index)">
                  <i class="fas fa-minus"></i>
                </button>
              </div>
            </div>
            <button class="btn btn-outline-primary mb-3" type="button" @click="addSelectField">
              <i class="fas fa-plus"></i> Add Field
            </button>
          </div>
          <!-- Sort Options -->
          <div>
            <h5>Sort</h5>
            <div class="form-row align-items-center mb-2">
              <div class="col">
                <input type="text" class="form-control" v-model="aggregate.sortBy" placeholder="Sort Field">
              </div>
              <div class="col">
                <select v-model="aggregate.sortDir" class="form-control">
                  <option value="asc">Ascending</option>
                  <option value="desc">Descending</option>
                </select>
              </div>
            </div>
          </div>
          <div>
            <h5>Limit</h5>
            <input type="number" class="form-control" v-model="aggregate.limit" placeholder="Limit number of records">
          </div>
          <button class="btn btn-primary" @click="runAggregateQuery">Run Query</button>
          <button class="btn btn-secondary ml-2" @click="showAggregateModal = false">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    new Vue({
      el: '#app',
      data: {
        mysqlDatabases: [],
        mongoDatabases: [],
        tables: [],
        selectedSource: '',
        selectedTable: '',
        records: [],
        tableHeaders: [],
        showButtons: false,
        currentPage: 1,
        totalPages: 1,
        recordsFetched: false,
        showAggregateModal: false,
        aggregate: {
          matchConditions: [
            { field: '', value: '' }
          ],
          selectFields: [''],
          sortBy: '',
          sortDir: 'asc',
          limit:''
        }
      },
      created() {
        this.fetchInitialData();
      },
      methods: {
        fetchInitialData() {
          fetch('init_dashboard.php')
            .then(res => res.json())
            .then(data => {
              this.mysqlDatabases = data.mysql || [];
              this.mongoDatabases = data.mongo || [];
            })
            .catch(() => {
              this.mysqlDatabases = ['MySQL Connection Failed'];
              this.mongoDatabases = ['MongoDB Connection Failed'];
            });
        },
        fetchTables() {
          this.showButtons = false;
          this.tables = [];
          this.selectedTable = '';

          fetch('fetch_tables.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ source: this.selectedSource })
          })
            .then(res => res.json())
            .then(data => {
              this.tables = data.length > 0 ? data : ['No tables or collections found'];
            });
        },
        loadRecords(page) {
          if (!this.selectedSource || !this.selectedTable) return;

          fetch('browse_records.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              source: this.selectedSource,
              table: this.selectedTable,
              page: page
            })
          })
            .then(res => res.json())
            .then(result => {
              this.records = result.data || [];
              this.recordsFetched = true;
              this.totalPages = Math.ceil(result.total / 100);
              this.currentPage = page;
              this.tableHeaders = this.records.length > 0 ? Object.keys(this.records[0]) : [];
            })
            .catch(error => {
              console.error("Error:", error);
              this.records = [];
              this.recordsFetched = true;
            });
        },
        // Aggregate Modal methods for match conditions
        addMatchCondition() {
          this.aggregate.matchConditions.push({ field: '', value: '' });
        },
        removeMatchCondition(index) {
          this.aggregate.matchConditions.splice(index, 1);
          if (this.aggregate.matchConditions.length === 0) {
            this.aggregate.matchConditions.push({ field: '', value: '' });
          }
        },
        // Aggregate Modal methods for select fields
        addSelectField() {
          this.aggregate.selectFields.push('');
        },
        removeSelectField(index) {
          this.aggregate.selectFields.splice(index, 1);
          if (this.aggregate.selectFields.length === 0) {
            this.aggregate.selectFields.push('');
          }
        },
        runAggregateQuery() {
          // Build match object from the dynamic fields
          let matchObj = {};
          this.aggregate.matchConditions.forEach(cond => {
            if (cond.field.trim() !== '') {
              matchObj[cond.field.trim()] = cond.value.trim();
            }
          });
          // Filter select fields array to remove empties
          let selectArr = this.aggregate.selectFields.filter(field => field.trim() !== '');

          let query = {
            match: matchObj,
            select: selectArr,
            sortBy: this.aggregate.sortBy,
            sortDir: this.aggregate.sortDir,
            limit: this.aggregate.limit,
          };
          console.log(query)
          axios.post('aggregate_records.php', {
            query: query
          })
          .then(res => {
            this.records = res.data;
            this.recordsFetched = true;
            this.tableHeaders = this.records.length ? Object.keys(this.records[0]) : [];
            this.showAggregateModal = false;
          })
          .catch(() => {
            alert("Failed to run query.");
          });
        }
      }
    });
  </script>
</body>
</html>
