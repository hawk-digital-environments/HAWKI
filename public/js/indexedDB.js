// Open or create the IndexedDB database
const openDB = (dbName, version, storeNames) => {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(dbName, version);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            storeNames.forEach(storeName => {
                if (!db.objectStoreNames.contains(storeName)) {
                    // Create each object store dynamically
                    db.createObjectStore(storeName, { keyPath: 'id' });
                }
            });
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
};

// Store data in IndexedDB
const storeData = async (dbName, storeName, data) => {
    try {
        const db = await openDB(dbName, 1, [storeName]);
        const transaction = db.transaction(storeName, 'readwrite');
        const store = transaction.objectStore(storeName);

        data.forEach(item => store.put(item));

        return new Promise((resolve, reject) => {
            transaction.oncomplete = () => resolve('Data stored successfully');
            transaction.onerror = () => reject(transaction.error);
        });
    } catch (error) {
        console.error('Error storing data:', error);
    }
};

// Retrieve data from IndexedDB
const retrieveData = async (dbName, storeName, key) => {
    try {
        const db = await openDB(dbName, 1, [storeName]);
        const transaction = db.transaction(storeName, 'readonly');
        const store = transaction.objectStore(storeName);
        const request = store.get(key);

        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    } catch (error) {
        console.error('Error retrieving data:', error);
    }
};

// Example usage
(async () => {
    const dbName = 'myDatabase';
    const storeName = 'keychains'; // Dynamic store name
    
    // Example data to store
    const data = [
        { id: 'user123', aiConvKey: 'some-encrypted-aiConvKey', roomsList: [ { room_id: 'room1', room_key: 'some-encrypted-roomKey1' } ] }
    ];

    // Store data
    await storeData(dbName, storeName, data);
    
    // Retrieve data
    const retrievedData = await retrieveData(dbName, storeName, 'user123');
    // console.log('Retrieved data:', retrievedData);
})();
