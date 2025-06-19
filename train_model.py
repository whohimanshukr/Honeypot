import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
import pickle

# Load the CSV
df = pd.read_csv('train_model.csv')

# Feature Engineering (same as used in live prediction)
df['device_type'] = df['device_type'].map({'Mobile': 0, 'Desktop': 1, 'Tablet': 2, 'Unknown': 3}).fillna(3)
df['username_length'] = df['username'].astype(str).apply(len)
df['ip_hash'] = df['ip'].astype(str).apply(lambda x: hash(x) % 1000)

# Select features and label
X = df[['device_type', 'username_length', 'ip_hash']]
y = df['prediction'].map({'normal': 0, 'suspicious': 1})  # Convert labels to binary

# Split data
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

# Train model
model = RandomForestClassifier(n_estimators=100, random_state=42)
model.fit(X_train, y_train)

# Save model
with open('model.pkl', 'wb') as f:
    pickle.dump(model, f)

print("✅ Model trained and saved as model.pkl")
