import pandas as pd
import numpy as np
import random
import pickle
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split

# Step 1: Generate dataset
entries = []
device_types = ['Mobile', 'Desktop', 'Tablet', 'Unknown']
usernames = ['admin', 'root', 'user', 'guest', 'john', 'jane', 'test']
ip_blocks = ['192.168.1.', '203.0.113.', '10.0.0.', '198.51.100.', '172.16.0.']

for _ in range(200000):
    suspicious = random.random() < 0.75  # 75% suspicious entries

    username = random.choice(usernames if suspicious else usernames[2:])
    ip = random.choice(ip_blocks) + str(random.randint(1, 254))
    device_type = random.choice(['Mobile', 'Desktop'] if suspicious else device_types)
    prediction = 'suspicious' if suspicious else 'normal'

    entries.append([username, ip, device_type, prediction])

df = pd.DataFrame(entries, columns=['username', 'ip', 'device_type', 'prediction'])
df.to_csv("logins.csv", index=False)
print("✅ CSV 'logins.csv' created with 200,000 entries.")

# Step 2: Train the model
device_type_map = {'Mobile': 0, 'Desktop': 1, 'Tablet': 2, 'Unknown': 3}
df['device_type_encoded'] = df['device_type'].map(device_type_map)
df['ip_hash'] = df['ip'].apply(lambda ip: hash(ip) % 1000)
df['username_length'] = df['username'].apply(len)
df['label'] = df['prediction'].map({'normal': 0, 'suspicious': 1})

X = df[['device_type_encoded', 'ip_hash', 'username_length']]
y = df['label']

X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2)

model = LogisticRegression(max_iter=1000)
model.fit(X_train, y_train)

with open("model.pkl", "wb") as f:
    pickle.dump(model, f)

print("✅ Model trained and saved as 'model.pkl'.")
