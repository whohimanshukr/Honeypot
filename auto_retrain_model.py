import pandas as pd
import numpy as np
import pickle
import re
from ipaddress import ip_address
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report
import mysql.connector

# Step 1: Connect to the database
db = mysql.connector.connect(
    host="localhost",
    user="your_db_user",
    password="your_db_password",
    database="your_db_name"
)

# Step 2: Load only reviewed data
query = """
SELECT username, ip, device_type, prediction
FROM attack_logs
WHERE prediction IN ('normal', 'suspicious')
"""

df = pd.read_sql(query, db)

# Step 3: Skip if less than 50 reviewed records
if df.shape[0] < 50:
    print(f"⏩ Skipping retrain: Only {df.shape[0]} reviewed entries found (min required: 50)")
    exit()

# Step 4: Feature Engineering
def extract_username_features(username):
    length = len(username)
    has_num = int(bool(re.search(r'\d', username)))
    return pd.Series([length, has_num])

def extract_ip_features(ip):
    try:
        ip_obj = ip_address(ip)
        return pd.Series([int(ip_obj.is_private), int(ip_obj.is_global)])
    except:
        return pd.Series([0, 0])

df[['username_length', 'username_has_num']] = df['username'].apply(extract_username_features)
df[['is_private_ip', 'is_global_ip']] = df['ip'].apply(extract_ip_features)

# Step 5: Encode device type
df['device_type'] = df['device_type'].fillna('unknown').str.lower()
device_types = df['device_type'].unique().tolist()
encoder = {name: i for i, name in enumerate(device_types)}
df['device_encoded'] = df['device_type'].map(encoder)

# Step 6: Encode labels
df['label'] = df['prediction'].map({'normal': 0, 'suspicious': 1})

# Step 7: Prepare training data
features = ['username_length', 'username_has_num', 'is_private_ip', 'is_global_ip', 'device_encoded']
X = df[features]
y = df['label']

# Step 8: Train/test split
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

# Step 9: Train model
clf = RandomForestClassifier(n_estimators=100, random_state=42)
clf.fit(X_train, y_train)

# Step 10: Save model
model_bundle = {
    'model': clf,
    'encoder': encoder
}

with open('model.pkl', 'wb') as f:
    pickle.dump(model_bundle, f)

print("✅ Retraining complete and model saved to model.pkl")
print(classification_report(y_test, clf.predict(X_test)))
